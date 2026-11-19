<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    /**
     * Generate social copy mapped for Facebook, Instagram, and YouTube.
     *
     * @return array{title: string, description: string, hashtags: string}
     */
    public function generatePostCopy(string $prompt, array $platforms = ['facebook', 'instagram', 'youtube']): array
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new RuntimeException('Enter a prompt to generate copy.');
        }

        $key = $this->apiKey();
        $model = config('services.gemini.model');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';

        $platformList = implode(', ', array_map('ucfirst', $platforms));

        $system = <<<TXT
You write one piece of social copy that will be posted to {$platformList} from the same form fields.

Return JSON only (no markdown fences) with keys:
- title: YouTube title AND Facebook Reel title. Max 90 characters. Punchy. 0–2 relevant emojis allowed. No hashtags.
- description: Caption/description body for Facebook post, Instagram caption, and YouTube description. Use short paragraphs and line breaks. Keep every emoji, symbol, and decorative character you include — do not replace them with text. Do NOT put hashtags in this field. Instagram-friendly length (aim under 1800 characters).
- hashtags: Array of 8–12 tags WITHOUT the # sign. Mix discoverable + niche. Safe for Instagram, Facebook, and YouTube.

Write in the same language as the user's prompt. No URLs unless the user asked for them.
TXT;

        $response = Http::timeout(45)
            ->acceptJson()
            ->post($url.'?key='.urlencode($key), [
                'systemInstruction' => [
                    'parts' => [['text' => $system]],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $prompt]],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.8,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini request failed: '.$response->body());
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if ($text === '') {
            throw new RuntimeException('Gemini returned empty copy.');
        }

        $parsed = $this->parseJson($text);

        $title = $this->cleanText((string) ($parsed['title'] ?? ''));
        $description = $this->cleanText((string) ($parsed['description'] ?? ''));
        $hashtags = $this->formatHashtags($parsed['hashtags'] ?? []);

        if ($title === '' && $description === '') {
            throw new RuntimeException('Gemini did not return a title or description.');
        }

        return [
            'title' => mb_substr($title, 0, 100),
            'description' => mb_substr($description, 0, 1800),
            'hashtags' => $hashtags,
        ];
    }

    private function parseJson(string $text): array
    {
        $trimmed = trim($text);
        $trimmed = preg_replace('/^```(?:json)?\s*/u', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/u', '', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Could not parse Gemini JSON.');
        }

        return $decoded;
    }

    private function cleanText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = trim($value);

        return $value;
    }

    private function formatHashtags(mixed $tags): string
    {
        if (is_string($tags)) {
            $tags = preg_split('/[\s,]+/u', $tags) ?: [];
        }

        if (! is_array($tags)) {
            return '';
        }

        $out = [];

        foreach ($tags as $tag) {
            $tag = ltrim(trim((string) $tag), '#');
            $tag = preg_replace('/[^\p{L}\p{N}_]+/u', '', $tag) ?? '';

            if ($tag === '') {
                continue;
            }

            $out[] = '#'.$tag;
        }

        $out = array_values(array_unique($out));

        return implode(' ', array_slice($out, 0, 12));
    }

    private function apiKey(): string
    {
        $key = config('services.gemini.api_key');

        if (! $key) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        return $key;
    }
}
