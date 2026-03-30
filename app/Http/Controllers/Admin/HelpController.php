<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpAttachment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpController extends Controller
{
    public function index(Request $request)
    {
        $query = HelpArticle::with('author:id,first_name,last_name')
            ->where('status', 'published');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'ilike', "%{$s}%")
                  ->orWhere('description', 'ilike', "%{$s}%")
                  ->orWhere('content', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $articles = $query->orderBy('sort_order')->orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/Help/Index', [
            'articles'   => $articles,
            'categories' => HelpArticle::CATEGORIES,
            'filters'    => $request->only(['search', 'category']),
            'isAdmin'    => in_array(auth()->user()->role, ['admin']),
        ]);
    }

    public function show(HelpArticle $article)
    {
        $article->load(['author:id,first_name,last_name', 'attachments']);

        return Inertia::render('Admin/Help/Show', [
            'article'    => $article,
            'categories' => HelpArticle::CATEGORIES,
            'isAdmin'    => in_array(auth()->user()->role, ['admin']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Help/Create', [
            'categories' => HelpArticle::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'category'    => 'required|string|in:' . implode(',', array_keys(HelpArticle::CATEGORIES)),
            'description' => 'nullable|string|max:500',
            'content'     => 'required|string',
            'status'      => 'required|string|in:published,draft',
            'files'       => 'nullable|array',
            'files.*'     => 'file|max:20480',
        ]);

        $article = HelpArticle::create(array_merge($validated, [
            'author_id' => auth()->id(),
        ]));

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('help-files', 'public');
                $article->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientMimeType(),
                ]);
            }
        }

        return redirect()->route('admin.help.show', $article)
            ->with('success', 'Article created.');
    }

    public function edit(HelpArticle $article)
    {
        $article->load('attachments');

        return Inertia::render('Admin/Help/Edit', [
            'article'    => $article,
            'categories' => HelpArticle::CATEGORIES,
        ]);
    }

    public function update(Request $request, HelpArticle $article)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'category'    => 'required|string|in:' . implode(',', array_keys(HelpArticle::CATEGORIES)),
            'description' => 'nullable|string|max:500',
            'content'     => 'required|string',
            'status'      => 'required|string|in:published,draft',
            'files'       => 'nullable|array',
            'files.*'     => 'file|max:20480',
        ]);

        $article->update($validated);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('help-files', 'public');
                $article->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientMimeType(),
                ]);
            }
        }

        return redirect()->route('admin.help.show', $article)
            ->with('success', 'Article updated.');
    }

    public function destroy(HelpArticle $article)
    {
        $article->delete();
        return redirect()->route('admin.help.index')
            ->with('success', 'Article deleted.');
    }

    public function deleteAttachment(HelpAttachment $attachment)
    {
        \Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();
        return back()->with('success', 'Attachment removed.');
    }
}
