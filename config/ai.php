<?php

return [
    'providers' => [
        'openai' => ['label'=>'OpenAI', 'url'=>'https://api.openai.com/v1', 'models'=>['gpt-4o-mini','gpt-4o','gpt-4.1-mini','gpt-4.1','gpt-5-mini','gpt-5','gpt-5-nano'], 'format'=>'openai'],
        'anthropic' => ['label'=>'Anthropic (Claude)', 'url'=>'https://api.anthropic.com/v1', 'models'=>['claude-sonnet-4-5','claude-haiku-4-5','claude-opus-4-1'], 'format'=>'anthropic'],
        'gemini' => ['label'=>'Google Gemini', 'url'=>'https://generativelanguage.googleapis.com/v1beta', 'models'=>['gemini-2.5-flash','gemini-2.5-pro','gemini-2.5-flash-lite'], 'format'=>'gemini'],
        'deepseek' => ['label'=>'DeepSeek', 'url'=>'https://api.deepseek.com', 'models'=>['deepseek-chat','deepseek-reasoner'], 'format'=>'openai'],
        'groq' => ['label'=>'Groq', 'url'=>'https://api.groq.com/openai/v1', 'models'=>['llama-3.3-70b-versatile','llama-3.1-8b-instant'], 'format'=>'openai'],
        'mistral' => ['label'=>'Mistral AI', 'url'=>'https://api.mistral.ai/v1', 'models'=>['mistral-small-latest','mistral-large-latest'], 'format'=>'openai'],
        'xai' => ['label'=>'xAI (Grok)', 'url'=>'https://api.x.ai/v1', 'models'=>['grok-4','grok-3-mini'], 'format'=>'openai'],
        'openrouter' => ['label'=>'OpenRouter', 'url'=>'https://openrouter.ai/api/v1', 'models'=>['openai/gpt-4o-mini','anthropic/claude-sonnet-4.5','google/gemini-2.5-flash'], 'format'=>'openai'],
        'together' => ['label'=>'Together AI', 'url'=>'https://api.together.xyz/v1', 'models'=>['meta-llama/Llama-3.3-70B-Instruct-Turbo'], 'format'=>'openai'],
        'fireworks' => ['label'=>'Fireworks AI', 'url'=>'https://api.fireworks.ai/inference/v1', 'models'=>['accounts/fireworks/models/llama-v3p3-70b-instruct'], 'format'=>'openai'],
        'perplexity' => ['label'=>'Perplexity', 'url'=>'https://api.perplexity.ai', 'models'=>['sonar','sonar-pro'], 'format'=>'openai'],
        'cohere' => ['label'=>'Cohere', 'url'=>'https://api.cohere.com/v2', 'models'=>['command-a-03-2025','command-r-plus-08-2024'], 'format'=>'cohere'],
        'openai-compatible' => ['label'=>'Custom / other OpenAI-compatible provider', 'url'=>'https://api.openai.com/v1', 'models'=>['gpt-4o-mini'], 'format'=>'openai'],
    ],
];
