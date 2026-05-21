<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\GuideSubmission;
use App\Services\GuideCatalogService;
use App\Services\GuideContentNormalizer;
use App\Services\GuidesNewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuidesController extends Controller
{
    public function __construct(
        private readonly GuidesNewsService $newsService,
        private readonly GuideCatalogService $catalogService,
        private readonly GuideContentNormalizer $normalizer,
    ) {}

    public function index(): Response
    {
        $this->catalogService->ensureSeeded();

        return Inertia::render('Guides/Index', $this->sharedGuideProps() + [
            'quickLinks' => GuideCatalogService::QUICK_LINKS,
            'seo' => [
                'title' => 'SkyBlock Guides - SkyblockHub',
                'description' => 'Browse practical Hypixel SkyBlock guides for progression, skills, dungeons, mining, farming, money making, events, mods, and updates.',
                'canonical' => route('guides'),
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $this->catalogService->ensureSeeded();

        $guide = Guide::published()->where('slug', $slug)->firstOrFail();

        $patches = $slug === 'news'
            ? $this->newsService->getRecentPatches(20)
            : [];

        return Inertia::render('Guides/Show', $this->sharedGuideProps() + [
            'guide' => $guide->toPublicArray(),
            'patches' => $patches,
            'seo' => $this->guideSeo($guide),
        ]);
    }

    public function createSubmission(): Response
    {
        $this->catalogService->ensureSeeded();

        return Inertia::render('Guides/Submit', $this->sharedGuideProps() + [
            'categories' => GuideCatalogService::CATEGORIES,
            'initialGuide' => $this->emptyGuideDraft(),
        ]);
    }

    public function storeSubmission(Request $request): RedirectResponse
    {
        $data = $this->validateSubmission($request);

        GuideSubmission::query()->create([
            ...$data,
            'type' => GuideSubmission::TYPE_NEW_ARTICLE,
            'status' => GuideSubmission::STATUS_PENDING,
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('guides')->with('status', 'Guide suggestion submitted for review.');
    }

    public function suggestEdit(string $slug): Response
    {
        $this->catalogService->ensureSeeded();

        $guide = Guide::published()->where('slug', $slug)->firstOrFail();

        return Inertia::render('Guides/SuggestEdit', $this->sharedGuideProps() + [
            'categories' => GuideCatalogService::CATEGORIES,
            'guide' => $guide->toPublicArray(),
        ]);
    }

    public function storeEditSuggestion(Request $request, string $slug): RedirectResponse
    {
        $guide = Guide::published()->where('slug', $slug)->firstOrFail();
        $data = $this->validateSubmission($request);

        GuideSubmission::query()->create([
            ...$data,
            'type' => GuideSubmission::TYPE_EDIT,
            'guide_id' => $guide->id,
            'status' => GuideSubmission::STATUS_PENDING,
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('guides.show', $guide->slug)->with('status', 'Guide edit submitted for review.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedGuideProps(): array
    {
        return [
            'guideGroups' => $this->catalogService->groups(),
            'guideExternalTools' => GuideCatalogService::EXTERNAL_TOOLS,
            'guideSearchIndex' => $this->catalogService->searchIndex(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function guideSeo(Guide $guide): array
    {
        $title = $guide->title.' Guide - SkyblockHub';
        $description = $guide->description ?: 'Read the '.$guide->title.' Hypixel SkyBlock guide on SkyblockHub.';

        return [
            'title' => $title,
            'description' => str($description)->limit(155, '')->toString(),
            'canonical' => route('guides.show', $guide->slug),
            'ogTitle' => $title,
            'ogDescription' => str($description)->limit(200, '')->toString(),
            'ogImage' => url('/img/logo-white.webp'),
            'ogType' => 'article',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request): array
    {
        $validated = $request->validate([
            'submitter_name' => ['nullable', 'string', 'max:120'],
            'submitter_contact' => ['nullable', 'string', 'max:190'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'max:80'],
            'category_label' => ['nullable', 'string', 'max:120'],
            'sections' => ['required', 'array'],
            'useful_links' => ['nullable', 'array'],
        ]);

        $category = array_key_exists($validated['category'], GuideCatalogService::CATEGORIES)
            ? $validated['category']
            : 'meta';

        return [
            'submitter_name' => $validated['submitter_name'] ?? null,
            'submitter_contact' => $validated['submitter_contact'] ?? null,
            'title' => trim($validated['title']),
            'slug' => str($validated['slug'] ?: $validated['title'])->slug()->toString(),
            'description' => $validated['description'] ?? null,
            'category' => $category,
            'category_label' => GuideCatalogService::CATEGORIES[$category],
            'sections' => $this->normalizer->sections($validated['sections']),
            'useful_links' => $this->normalizer->usefulLinks($validated['useful_links'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyGuideDraft(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'description' => '',
            'category' => 'meta',
            'sections' => [
                [
                    'id' => 'overview',
                    'heading' => 'Overview',
                    'level' => 2,
                    'blocks' => [
                        ['type' => 'paragraph', 'text' => ''],
                    ],
                ],
            ],
            'usefulLinks' => [],
        ];
    }
}
