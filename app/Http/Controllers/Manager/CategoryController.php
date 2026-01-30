<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('label')->get();

        return Inertia::render('Manager/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Manager/Categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:75',
            'description' => 'nullable|string|max:500',
        ]);

        // Generate unique slug
        $slugBase = Str::slug($validated['label']);
        $slug = $slugBase;
        $acc = 1;
        while (Category::where('slug', $slug)->exists()) {
            $acc++;
            $slug = $slugBase.'-'.$acc;
        }

        Category::create([
            'slug' => $slug,
            'label' => $validated['label'],
            'description' => $validated['description'] ?: null,
            'created_by' => $request->user()->username,
        ]);

        return redirect()->route('manager.categories.index')
            ->with('success', 'La catégorie a été créée avec succès');
    }

    public function edit(string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        return Inertia::render('Manager/Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'label' => 'required|string|max:75',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update([
            'label' => $validated['label'],
            'description' => $validated['description'] ?: null,
        ]);

        return back()->with('success', 'Les changements ont bien été sauvegardés');
    }

    public function destroy(string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $category->delete();

        return redirect()->route('manager.categories.index')
            ->with('success', 'La catégorie a bien été supprimée');
    }
}
