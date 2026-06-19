<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\LocaleService;
use App\Services\NotificationTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
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
            'languages' => collect(LocaleService::LANGUAGES)
                ->map(fn (string $code) => [
                    'code' => $code,
                    'name' => $this->languageName($code),
                ])
                ->all(),
            'notificationTemplates' => $templates,
        ]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $keys = array_keys(NotificationTemplateService::definitions());
        $languages = LocaleService::LANGUAGES;

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

    private function languageName(string $code): string
    {
        return [
            'en' => 'English',
            'de' => 'German',
            'it' => 'Italian',
            'fr' => 'French',
            'ar' => 'Arabic',
            'tr' => 'Turkish',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'sr' => 'Serbian',
            'cs' => 'Czech',
            'es' => 'Spanish',
            'nl' => 'Dutch',
        ][$code] ?? strtoupper($code);
    }
}
