<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Category;
class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();
        return view('panel.profile.index',[ 'categories' => $categories]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $request->validate([
        //     'name' => 'required',
        //     'phone' => 'required',
        //     'cpf' => 'required',
        // ]);

        // $data = $request->except(['email', 'old_password', 'new_password']);

        // if(!empty($request->old_password)) {
        //     if (Hash::check($request->old_password, auth()->user()->password))
        //     {
        //         $request->validate([
        //             'new_password' => 'required|min:6',
        //         ]);

        //         $data['password'] = $request->new_password;
        //     }else{
        //         return back()->with('error', __('admin.Password_not_match'))->withInput();
        //     }
        // }

        // if(auth()->user()->update($data)) {
        //     return back()->with(['success' => __('admin.Successfully_changed_data')])->withInput();
        // }
        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
