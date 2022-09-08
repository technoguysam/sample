<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityLogEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseProductRequest;
use App\Http\Resources\PurchaseProduct as PurchaseProductResource;
use App\Models\Item;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\Tax;
use Illuminate\Http\Request;

class PurchaseProductController extends Controller
{
    public function __construct()
    {
        parent::generateAllMiddlewareByPermission('purchaseProducts');
        $this->middleware(['role:'. 'Admin|Store Manager'])->only(['changeStatusPr']);
    }

    public function changeStatusPr($id, PurchaseProductRequest $request)
    {
        $data['success'] = true;
        $data['message'] = '';
        $data['data'] = [];
        try {
            $data['success'] = true;
            $purchaseProduct = PurchaseProduct::findOrFail($id);
            $values = $request->all();
            $purchaseProduct->update($values);
            event(new ActivityLogEvent('Edit', 'Purchase Product', $purchaseProduct->id));
            $this->changeStatusOfPurchaseRequestFromPpStatus($values['status'],$purchaseProduct->purchase_id);
            $data['message'] = "Updated successfully.";
            $data['data'] = new PurchaseProductResource($purchaseProduct);
        } catch (\Exception $e) {
            return response(['success' => false, "message" => trans('messages.error_server'), "data" => $e], 500);
        }
        return $data;
    }

    public function changeStatusOfPurchaseRequestFromPpStatus($status, $prid)
    {
        $products = PurchaseProduct::where('purchase_id', $prid)->get();
        if ($status === 'Approved') {
            $checkKey = true;
            foreach ($products as $single) {
                if ($single->status !== 'Approved') {
                    $checkKey = false;
                }
            }
            $purchase = Purchase::where('id', $prid)->first();
            if ($checkKey) {
                $purchase->status = 'Approved';
            } else {
                $purchase->status = 'Partial Approved';
            }
            $purchase->seen_status = 'seen';
            $purchase->save();
        } elseif ($status === 'Rejected') {
            $checkKey = true;
            foreach ($products as $single) {
                if ($single->status !== 'Rejected') {
                    $checkKey = false;
                }
            }
            $purchase = Purchase::where('id', $prid)->first();
            if ($checkKey) {
                $purchase->status = 'Rejected';
            } else {
                $purchase->status = 'Partial Rejected';
            }
            $purchase->seen_status = 'seen';
            $purchase->save();
        }
    }

    public function index()
    {
        $data['success'] = true;
        $data['message'] = '';
        $data['data'] = [];
        try {
            $data['success'] = true;
            $data['data'] = PurchaseProductResource::collection(PurchaseProduct::all());
        } catch (\Exception $e) {
            return response(['success' => false, "message" => trans('messages.error_server'), "data" => $e], 500);
        }
        return $data;
    }

    public function create()
    {

    }

    public function store(PurchaseProductRequest $request)
    {
        $data['success'] = true;
        $data['message'] = '';
        $data['data'] = [];
        try {
            $data['success'] = true;
            $values = $request->all();
            if ($request->quantity === null) {
                $values['quantity'] = 1;
            }
            $purchase = Purchase::findOrFail($request->purchase_id);
            $product = Product::findOrFail($request->product_id);
            if ($request->product_variant_id != null) {
                $variant = ProductVariant::findOrFail($request->product_variant_id);
            }
            $price = $variant->price ?? $product->cost_price;
            $values['price'] = $price;
            $values['total_price'] = $values['quantity'] * $price;
            $purchaseProduct = new PurchaseProduct($values);
            $purchaseProduct->save();
            $purchaseProduct->taxCalculate();
            $purchase->total();
            event(new ActivityLogEvent('Add', 'Purchase Product', $purchaseProduct->id));
            $data['message'] = "Purchase Product added successfully.";
            $data['data'] = new PurchaseProductResource($purchaseProduct);
        } catch (\Exception $e) {
            return response(['success' => false, "message" => trans('messages.error_server'), "data" => $e], 500);
        }
        return $data;
    }

    public function show($id)
    {
        $data['success'] = true;
        $data['message'] = '';
        $data['data'] = [];
        try {
            $data['success'] = true;
            $data['data'] = new PurchaseProductResource(PurchaseProduct::findOrFail($id));
        } catch (\Exception $e) {
            return response(['success' => false, "message" => trans('messages.error_server'), "data" => $e], 500);
        }
        return $data;
    }

    public function edit($id)
    {

    }

    public function update($id, PurchaseProductRequest $request)
    {
        $data['success'] = true;
        $data['message'] = '';
        $data['data'] = [];
        try {
            $data['success'] = true;
            $purchaseProduct = PurchaseProduct::findOrFail($id);
            $purchase = Purchase::findOrFail($purchaseProduct->purchase_id);
            $values = $request->all();
            if ($request->product_id !== null) {
                $product = Product::findOrFail($request->product_id);
            } else {
                $product = $purchaseProduct->product;
            }
            if ($request->product_variant_id != null) {
                $variant = ProductVariant::findOrFail($request->product_variant_id);
            } elseif ($purchaseProduct->product_variant_id !== null) {
                $variant = $purchaseProduct->productVariant;
            }
            $price = $variant->price ?? $product->cost_price;
            $values['price'] = $price;
            if (($request->quantity !== null)) {
                $values['total_price'] = $request->quantity * $price;
            }
            $purchaseProduct->update($values);
            $purchaseProduct->taxCalculate();
            $purchase->total();
            if ($request->purchase_id !== null) {
                $purchase1 = Purchase::findOrFail($request->purchase_id);
                $purchase1->total();
            }
            event(new ActivityLogEvent('Edit', 'Purchase Product', $purchaseProduct->id));
            $data['message'] = "Updated successfully.";
            $data['data'] = new PurchaseProductResource($purchaseProduct);
        } catch (\Exception $e) {
            return response(['success' => false, "message" => trans('messages.error_server'), "data" => $e], 500);
        }
        return $data;
    }

    public function destroy($id)
    {
        $data['success'] = true;
        $data['message'] = '';
        $data['data'] = [];
        try {
            $data['success'] = true;
            $purchaseProduct = PurchaseProduct::findOrFail($id);
            $purchase = $purchaseProduct->purchase;
            $purchaseProduct->delete();
            $purchase->total();
            event(new ActivityLogEvent('Delete', 'Purchase Product', $id));
            $data['message'] = "Deleted successfully.";
        } catch (\Exception $e) {
            return response(['success' => false, "message" => trans('messages.error_server'), "data" => $e], 500);
        }
        return $data;
    }
}
