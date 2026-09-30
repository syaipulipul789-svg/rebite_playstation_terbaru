<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Support\Barcode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductMasterController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $products = Product::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->when($request->boolean('low_stock'), fn ($q) => $q->orderBy('stock'))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $lowStock = Product::query()->active()->orderBy('stock')->limit(6)->get()
            ->filter(fn (Product $p) => $p->isLowStock())
            ->values();

        return view('owner.products.index', [
            'products' => $products,
            'lowStock' => $lowStock,
            'filters' => $request->only(['category', 'low_stock']),
        ]);
    }

    /**
     * Sheet label barcode siap cetak untuk ditempel di kemasan produk.
     * Produk tanpa barcode sengaja dikecualikan — owner harus mengisinya
     * dulu di form produk, ataubiarkan auto-generate saat produk dibuat.
     */
    public function labels(Request $request): View
    {
        $products = Product::query()
            ->active()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->withBarcode()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('owner.products.labels', [
            'products' => $products,
            'missingCount' => Product::query()->active()->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))->count(),
            'category' => $request->string('category')->toString(),
            'categories' => ProductCategory::cases(),
        ]);
    }

    public function create(): View
    {
        return view('owner.products.form', [
            'product' => new Product([
                'category' => ProductCategory::SNACK,
                'price' => 0,
                'stock' => 0,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create([
            ...$request->safe()->except('barcode'),
            // Barcode kosong diisi otomatis dari id produk supaya produk
            // tetap bisa dipindai pelanggan tanpa setting manual.
            'barcode' => $request->input('barcode') ?: null,
        ]);

        $product->update(['barcode' => $product->barcode ?: Barcode::generateFor($product->id)]);

        $this->audit->record(
            user: $request->user(),
            shift: null,
            event: AuditLog::EVENT_MASTER_UPDATED,
            description: "Produk baru: {$product->name}",
            context: ['product_id' => $product->id],
        );

        return redirect()->route('owner.products.index')
            ->with('success', "Produk {$product->name} berhasil ditambahkan.");
    }

    public function edit(Product $product): View
    {
        return view('owner.products.form', ['product' => $product]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('owner.products.index')
            ->with('success', "Produk {$product->name} berhasil diperbarui.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->sessionItems()->exists()
            ? $product->update(['is_active' => false])
            : $product->delete();

        return back()->with('success', "Produk {$name} dinonaktifkan / dihapus.");
    }

    /**
     * Tambah / kurangi stok cepat.
     */
    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer', 'min:-9999', 'max:9999', 'not_in:0'],
        ], [
            'delta.not_in' => 'Jumlah perubahan stok tidak boleh nol.',
        ]);

        $newStock = max(0, $product->stock + $validated['delta']);
        $product->update(['stock' => $newStock]);

        $this->audit->record(
            user: $request->user(),
            shift: null,
            event: AuditLog::EVENT_MASTER_UPDATED,
            description: sprintf(
                'Stok %s diubah %+d menjadi %d',
                $product->name,
                $validated['delta'],
                $newStock,
            ),
            context: ['product_id' => $product->id, 'stock' => $newStock],
        );

        return back()->with('success', "Stok {$product->name} kini {$newStock}.");
    }
}
