<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BillboardController extends Controller
{
    private const STORAGE_FILE = 'billboard.json';

    private const MAX_BILLBOARDS = 5;

    public function index(Request $request)
    {
        $billboards = $this->getBillboards();

        return Inertia::render('Manager/Billboard/Index', [
            'billboards' => $billboards,
        ]);
    }

    public function create(Request $request)
    {
        $billboards = $this->getBillboards();

        if (count($billboards) >= self::MAX_BILLBOARDS) {
            return redirect()->route('manager.billboard.index')
                ->with('error', 'Maximum 5 annonces autorisées');
        }

        $colorOptions = [
            ['value' => 'gradient-dark-red', 'label' => 'Rouge'],
            ['value' => 'gradient-calm-darya', 'label' => 'Bleu/Violet'],
            ['value' => 'gradient-purple-dream', 'label' => 'Violet'],
            ['value' => 'gradient-sexy-blue', 'label' => 'Bleu'],
            ['value' => 'gradient-emerald-water', 'label' => 'Vert/Bleu'],
        ];

        return Inertia::render('Manager/Billboard/Create', [
            'colorOptions' => $colorOptions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:60',
            'button' => 'required|string|max:30',
            'url' => 'required|string|max:255',
            'icon' => 'nullable|string|max:30',
            'color' => 'required|string',
        ]);

        $billboards = $this->getBillboards();

        if (count($billboards) >= self::MAX_BILLBOARDS) {
            return back()->withErrors(['error' => 'Maximum 5 annonces autorisées']);
        }

        $identifier = Str::random(20);
        while (collect($billboards)->contains('identifier', $identifier)) {
            $identifier = Str::random(20);
        }

        $billboards[] = [
            'identifier' => $identifier,
            'title' => $validated['title'],
            'button' => $validated['button'],
            'url' => $validated['url'],
            'icon' => $validated['icon'] ?? '',
            'color' => $validated['color'],
        ];

        $this->saveBillboards($billboards);

        return redirect()->route('manager.billboard.index')
            ->with('success', 'Annonce créée');
    }

    public function edit(Request $request, string $identifier)
    {
        $billboards = $this->getBillboards();
        $billboard = collect($billboards)->firstWhere('identifier', $identifier);

        if (! $billboard) {
            abort(404);
        }

        $colorOptions = [
            ['value' => 'gradient-dark-red', 'label' => 'Rouge'],
            ['value' => 'gradient-calm-darya', 'label' => 'Bleu/Violet'],
            ['value' => 'gradient-purple-dream', 'label' => 'Violet'],
            ['value' => 'gradient-sexy-blue', 'label' => 'Bleu'],
            ['value' => 'gradient-emerald-water', 'label' => 'Vert/Bleu'],
        ];

        return Inertia::render('Manager/Billboard/Edit', [
            'billboard' => $billboard,
            'colorOptions' => $colorOptions,
        ]);
    }

    public function update(Request $request, string $identifier)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:60',
            'button' => 'required|string|max:30',
            'url' => 'required|string|max:255',
            'icon' => 'nullable|string|max:30',
            'color' => 'required|string',
        ]);

        $billboards = $this->getBillboards();
        $found = false;

        foreach ($billboards as &$billboard) {
            if ($billboard['identifier'] === $identifier) {
                $billboard['title'] = $validated['title'];
                $billboard['button'] = $validated['button'];
                $billboard['url'] = $validated['url'];
                $billboard['icon'] = $validated['icon'] ?? '';
                $billboard['color'] = $validated['color'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            abort(404);
        }

        $this->saveBillboards($billboards);

        return redirect()->route('manager.billboard.index')->with('success', 'Annonce modifiée');
    }

    public function destroy(Request $request, string $identifier)
    {
        $billboards = $this->getBillboards();
        $billboards = array_values(array_filter($billboards, function ($b) use ($identifier) {
            return $b['identifier'] !== $identifier;
        }));

        $this->saveBillboards($billboards);

        return redirect()->route('manager.billboard.index')
            ->with('success', 'Annonce supprimée');
    }

    private function getBillboards(): array
    {
        if (! Storage::disk('local')->exists(self::STORAGE_FILE)) {
            return [];
        }

        $content = Storage::disk('local')->get(self::STORAGE_FILE);

        return json_decode($content, true) ?? [];
    }

    private function saveBillboards(array $billboards): void
    {
        Storage::disk('local')->put(self::STORAGE_FILE, json_encode($billboards, JSON_PRETTY_PRINT));
    }
}
