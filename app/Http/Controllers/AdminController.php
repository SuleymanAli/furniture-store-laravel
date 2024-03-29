<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;
use App\ProductTranslation;
use App\ProductKeyword;
use App\Category;
use App\User;
use App\Role;
use Storage;
use Session;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Intervention\Image\Facades\Image;

class AdminController extends Controller
{
    /* Create, Show, Update, Delete Of Products From The Admin Panel */

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Product::all();
        $categories = Category::all();

        return view('admin.product.index', ['products' => $products]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::all();

        return view('admin.product.create', ['categories' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validation
        $this->validate($request, [
            'price' => 'required|numeric',
            'image'  => 'required|image|max:1999',
            'category_id' => 'sometimes|integer',
            'title' => 'required|unique:product_translations,title',
            'description' => 'required',
            'language' => 'required',
        ]);

        // Product Store On The Database
        $product = new Product();
        $productTranslation = new ProductTranslation();

        $product->price = $request->price;
        $product->category_id = $request->category_id;

        // Save Our Image
        if($request->hasFile('image')){
            $file = Input::file('image');

            $image = Image::make($file);

            Response::make($image->encode('jpeg'));
        }

        $product->image = $image;

        $product->save();

        $productTranslation->title = $request->title;
        $productTranslation->description = $request->description;

        $slug = str_slug($request->title, '-');
        $productTranslation->slug = $slug;

        $productTranslation->language = $request->language;
        $productTranslation->product()->associate($product);
        $productTranslation->save();

        return redirect()->route('admin.index')->with('success', 'Product Added');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $product = Product::find($id);

        return view('admin.product.show', ['product' => $product]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $product = Product::find($id);
        $categories = Category::pluck('name', 'id');

        return view('admin.product.edit', ['product' => $product, 'categories' => $categories]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Get Category By ID
        $product = Product::find($id);

        // Validate Data
        if (!$request->image) {
            $this->validate($request, [
                'price' => 'required|numeric',
                'image'  => 'image|nullable|max:1999',
                'category_id' => 'required|integer',
            ]);
        } else {
            $this->validate($request, [
                'price' => 'required|numeric',
                'category_id' => 'required|integer',
            ]);
        }

        // Save Our Image
        if($request->hasFile('image')){
            $file = Input::file('image');

            $image = Image::make($file);

            Response::make($image->encode('jpeg'));
        }

        // Saving Data To The Database
        $product->price = $request->input('price');
        $product->category_id = $request->input('category_id');
        if ($request->hasFile('image')) {
            $product->image = $image;
        }

        $product->save();

        return redirect()->route('admin.index')->with('success', 'Product Updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        $product->delete();

        Session::flash('success','This Product Deleted Successfully');

        return redirect()->route('admin.index');
    }

    public function showProductImage($id)
    {
        $product = Product::find($id);

        $image = Image::make($product->image);

        $response = Response::make($image->encode('jpeg'));

        $response->header('Content-Type', 'image/jpeg');

        return $response;
    }

    /*  */

    // Add Another Language The Current Product By ID (GET)
    public function createMultilang($id)
    {
        $product = Product::find($id);

        return view('admin.product.create-multilang', ['product' => $product]);
    }

    // Add Another Language The Current Product By ID (POST)
    public function storeMultilang(Request $request, $product_id)
    {
        // Validation
        $this->validate($request, [
            'title' => 'required|unique:product_translations,title',
            'description' => 'required',
            'language' => 'required',
        ]);

        // Store The Database
        $productTranslation = new ProductTranslation;
        $product = Product::find($product_id);

        $productTranslation->title = $request->title;
        $productTranslation->description = $request->description;

        $slug = str_slug($request->title, '-');
        $productTranslation->slug = $slug;


        $productTranslation->language = $request->language;
        $productTranslation->product()->associate($product);
        $productTranslation->save();

        // Keywords
        $keyword = array_map('trim', explode(",", $request->keyword));

        $data = array();
        
        foreach ($keyword as $key) { 

            $data[] = [
                'product_translation_id' => $productTranslation->id,
                'name' => $key
            ];
        }

        ProductKeyword::insert($data);

        return redirect()->route('admin.index')->with('success', 'Language Added To The Product');
    }

    // Edit Available Language Of Current Product By ID (GET)
    public function editMultilang($id)
    {
        $translation = ProductTranslation::find($id);

        return view('admin.product.edit-multilang', ['translation' => $translation]);
    }

    // Edit Available Language Of Current Product By ID (POST)
    public function updateMultilang(Request $request, $id)
    {
        $productTranslation = ProductTranslation::find($id);

        if ($request->title != $productTranslation->title) {
            $this->validate($request, [
                'title' => 'required|unique:product_translations,title',
                'description' => 'required',
                'language' => 'required',
            ]);
        } else {
            $this->validate($request, [
                'description' => 'required',
                'language' => 'required',
            ]); 
        }

        $productTranslation->title = $request->title;
        $productTranslation->description = $request->description;

        $slug = str_slug($request->title, '-');
        $productTranslation->slug = $slug;

        $productTranslation->language = $request->language;

        $productTranslation->save();

        Session::flash('success', 'Product Translation Updated');

        return redirect()->route('admin.show', $productTranslation->product->id); 
    }

    // Delete This Language Of Product By ID (DELETE)
    public function destroyMultilang($id)
    {
        $productTranslation = ProductTranslation::find($id);

        $productTranslation->delete();

        Session::flash('success','This Product Translation Deleted Successfully');

        return redirect()->route('admin.show', $productTranslation->product->id);
    }

    /* User Control List */

    // Show All Users With Own Roles In Admin Panel User Roles Area
    public function getAdminPage()
    {
        $users = User::all();

        return view('admin.admin', ['users' => $users]);
    }

    // Assign Role To User 
    public function AssignRole(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        $user->roles()->detach();

        if ($request['role_user']) {
            $user->roles()->attach(Role::where('name', 'User')->first());
        }

        if ($request['role_author']) {
            $user->roles()->attach(Role::where('name', 'Author')->first());
        }

        if ($request['role_admin']) {
            $user->roles()->attach(Role::where('name', 'Admin')->first());
        }

        if ($request['role_f_user']) {
            $user->roles()->attach(Role::where('name', 'F.User')->first());
        }

        return redirect()->back();
    }
}
