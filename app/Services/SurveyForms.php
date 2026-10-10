<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SurveyForms
{
    public const TYPES = ['short_text' => 'Short text', 'long_text' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'single_choice' => 'Single choice', 'multiple_choice' => 'Multiple choice', 'rating' => 'Rating (1–5)'];

    public static function questions(Request $request): array
    {
        $data = $request->validate([
            'questions' => 'required|array|min:1|max:50', 'questions.*' => 'required|array:id,label,type,required,help,options,options_text',
            'questions.*.id' => 'nullable|uuid|distinct', 'questions.*.label' => 'required|string|max:255',
            'questions.*.type' => 'required|in:'.implode(',', array_keys(self::TYPES)), 'questions.*.required' => 'nullable|boolean',
            'questions.*.help' => 'nullable|string|max:1000', 'questions.*.options_text' => 'nullable|string|max:10000',
            'questions.*.options' => 'nullable|array|max:30', 'questions.*.options.*' => 'required|string|max:255',
        ]);
        $out = [];
        foreach ($data['questions'] as $index => $question) {
            $options = isset($question['options_text']) ? preg_split('/\r\n|\r|\n/', $question['options_text']) : ($question['options'] ?? []);
            $options = array_values(array_filter(array_map('trim', $options), fn ($option) => $option !== ''));
            if (in_array($question['type'], ['single_choice', 'multiple_choice'], true)) {
                if (count($options) < 2 || count($options) > 30 || count(array_unique($options)) !== count($options) || collect($options)->contains(fn ($option) => mb_strlen($option) > 255)) {
                    throw ValidationException::withMessages(['questions.'.$index.'.options_text' => 'Add 2–30 different choices, one per line (maximum 255 characters each).']);
                }
            } else $options = [];
            $out[] = ['id' => $question['id'] ?? (string)Str::uuid(), 'label' => $question['label'], 'type' => $question['type'], 'required' => (bool)($question['required'] ?? false), 'help' => $question['help'] ?? '', 'options' => $options];
        }
        return $out;
    }

    public static function open(object $survey): bool
    {
        return $survey->status === 'published' && (!$survey->opens_at || now()->gte($survey->opens_at)) && (!$survey->closes_at || now()->lt($survey->closes_at));
    }

    public static function answers(array $questions, mixed $answers): array
    {
        $answers ??= [];
        if (!is_array($answers)) throw ValidationException::withMessages(['answers' => 'Submit answers using the survey form.']);
        $known = array_column($questions, 'id');
        if (array_diff(array_keys($answers), $known)) throw ValidationException::withMessages(['answers' => 'This response contains an unknown question. Reload the survey and try again.']);
        $rules = [];
        foreach ($questions as $question) {
            $key = 'answers.'.$question['id']; $rules[$key] = [$question['required'] ? 'required' : 'nullable'];
            $rules[$key] = array_merge($rules[$key], match ($question['type']) {
                'short_text' => ['string', 'max:500'], 'long_text' => ['string', 'max:10000'],
                'number' => ['numeric', 'between:-999999999,999999999'], 'date' => ['date_format:Y-m-d'],
                'rating' => ['integer', 'between:1,5'], 'single_choice' => ['string', \Illuminate\Validation\Rule::in($question['options'])],
                'multiple_choice' => ['array', 'max:'.count($question['options'])],
            });
            if ($question['type'] === 'multiple_choice') $rules[$key.'.*'] = ['required', 'string', 'distinct', \Illuminate\Validation\Rule::in($question['options'])];
        }
        $attributes = [];
        foreach ($questions as $question) $attributes['answers.'.$question['id']] = $question['label'];
        $validated = Validator::make(['answers' => $answers], $rules, [], $attributes)->validate();
        $out = [];
        foreach ($questions as $question) $out[$question['id']] = $validated['answers'][$question['id']] ?? null;
        return $out;
    }

    public static function summary(array $questions, iterable $responses): array
    {
        $out = [];
        foreach ($questions as $question) $out[$question['id']] = ['label' => $question['label'], 'type' => $question['type'], 'answered' => 0, 'sum' => 0, 'choices' => array_fill_keys($question['type'] === 'rating' ? ['1', '2', '3', '4', '5'] : $question['options'], 0)];
        foreach ($responses as $response) {
            foreach (json_decode($response->answers, true) as $id => $answer) {
                if (!isset($out[$id]) || $answer === null || $answer === '' || $answer === []) continue;
                $out[$id]['answered']++;
                if (in_array($out[$id]['type'], ['rating', 'number'], true)) $out[$id]['sum'] += (float)$answer;
                foreach (is_array($answer) ? $answer : [$answer] as $choice) if (is_scalar($choice) && array_key_exists((string)$choice, $out[$id]['choices'])) $out[$id]['choices'][(string)$choice]++;
            }
        }
        foreach ($out as &$item) $item['average'] = $item['answered'] && in_array($item['type'], ['rating', 'number'], true) ? round($item['sum'] / $item['answered'], 2) : null;
        return array_values($out);
    }
}
