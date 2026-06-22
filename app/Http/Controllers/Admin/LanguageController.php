<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\NotificationTemplate;
use App\Services\LocaleService;
use App\Services\NotificationTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(): Response
    {
        $saved = NotificationTemplate::query()
            ->get(['key', 'language', 'message'])
            ->groupBy('key');

        $templates = collect(NotificationTemplateService::definitions())
            ->map(function (array $definition, string $key) use ($saved) {
                $messages = $saved->get($key, collect())
                    ->mapWithKeys(fn (NotificationTemplate $template) => [
                        $template->language => $template->message,
                    ])
                    ->all();

                return [
                    'key' => $key,
                    'event' => $definition['event'],
                    'label' => $definition['label'],
                    'defaultMessage' => $definition['default'],
                    'placeholders' => $definition['placeholders'],
                    'messages' => $messages,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/languages/index', [
            'languages' => Language::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Language $language) => $this->formatLanguage($language))
                ->all(),
            'activeLanguages' => $this->locales->languageOptions(),
            'notificationTemplates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        $validated = $this->validateLanguage($request);

        Language::create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? ((Language::max('sort_order') ?? -1) + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return to_route('admin.languages.index')->with('status', 'Language created.');
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $validated = $this->validateLanguage($request, $language);

        if ($language->code === 'en') {
            $validated['is_active'] = true;
        }

        unset($validated['code']);
        $language->update($validated);

        return to_route('admin.languages.index')->with('status', 'Language updated.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        if ($language->code === 'en') {
            return back()->withErrors(['language' => 'English is the required fallback language and cannot be deleted.']);
        }

        $language->delete();

        return to_route('admin.languages.index')->with('status', 'Language deleted.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $keys = array_keys(NotificationTemplateService::definitions());
        $languages = $this->locales->activeLanguageCodes();

        $validated = $request->validate([
            'messages' => ['required', 'array'],
            'messages.*' => ['array'],
            'messages.*.*' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated['messages'] as $key => $messages) {
            if (! in_array($key, $keys, true) || ! is_array($messages)) {
                continue;
            }

            foreach ($messages as $language => $message) {
                if (! in_array($language, $languages, true)) {
                    continue;
                }

                $message = trim((string) $message);

                if ($message === '') {
                    NotificationTemplate::where('key', $key)
                        ->where('language', $language)
                        ->delete();

                    continue;
                }

                NotificationTemplate::updateOrCreate(
                    ['key' => $key, 'language' => $language],
                    ['message' => $message],
                );
            }
        }

        return to_route('admin.languages.index')->with('status', 'Notification messages updated.');
    }

    private function validateLanguage(Request $request, ?Language $language = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,6})?$/',
                Rule::unique('languages', 'code')->ignore($language?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'native_name' => ['nullable', 'string', 'max:100'],
            'flag' => ['nullable', 'string', 'max:20'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    private function formatLanguage(Language $language): array
    {
        return [
            'id' => $language->id,
            'code' => $language->code,
            'name' => $language->name,
            'nativeName' => $language->native_name,
            'flag' => $language->flag,
            'direction' => $language->direction,
            'sortOrder' => $language->sort_order,
            'isActive' => $language->is_active,
        ];
    }
}
