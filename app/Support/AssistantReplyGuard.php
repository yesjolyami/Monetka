<?php

namespace App\Support;

final class AssistantReplyGuard
{
    /**
     * @return list<string>
     */
    public static function outboundForbiddenPatterns(): array
    {
        return [
            '/```/',
            '/\bimport\s+[a-zA-Z_][\w.]*/i',
            '/\bfrom\s+[a-zA-Z_][\w.]*\s+import\b/i',
            '/\bdef\s+[a-zA-Z_]\w*\s*\(/i',
            '/\bclass\s+[a-zA-Z_]\w*\s*[:(]/i',
            '/\bfunction\s+[a-zA-Z_]\w*\s*\(/i',
            '/\bconsole\.log\s*\(/i',
            '/\bsocket\.(AF_INET|socket|SOCK_)/i',
            '/\bbind\s*\(\s*[\'"]0\.0\.0\.0/i',
            '/\b(UDP|TCP)\s*server\b/i',
            '/\bSELECT\s+.+\s+FROM\b/i',
            '/\bcurl\s+https?:\/\//i',
            '/\b#!/',
            '/\bpowershell\b/i',
            '/\bnpm\s+(install|run)\b/i',
            '/\bpip\s+install\b/i',
            '/\bDockerfile\b/i',
        ];
    }

    /**
     * Ловим до API, чтобы не жечь токены на инъекциях и код-запросах.
     *
     * @return list<string>
     */
    public static function inboundBlockedPatterns(): array
    {
        return [
            '/игнорир\w*\s+(все\s+)?(предыдущ\w*|прошл\w*|системн\w*)\s+инструк/iu',
            '/ignore\s+(all\s+)?(previous|prior|above)\s+instructions/i',
            '/\bjailbreak\b/i',
            '/\bDAN\b/',
            '/режим\s+разработчика/iu',
            '/забудь\s+(все\s+)?(правила|инструкции)/iu',
            '/напиши\s+(простой\s+)?(udp|tcp|http)\s*сервер/iu',
            '/write\s+(a\s+)?(simple\s+)?(udp|tcp|http)\s*server/i',
            '/\b(python|javascript|typescript|php|java|golang|rust)\b.{0,40}\b(код|скрипт|server|сервер|функци)/iu',
            '/\b(код|скрипт|программу)\b.{0,40}\b(на\s+)?(python|javascript|php|bash|powershell)\b/iu',
            '/```/',
            '/\bimport\s+socket\b/i',
            '/\bpip\s+install\b/i',
            '/\bnpm\s+install\b/i',
        ];
    }

    public static function looksUnsafeOutbound(string $text): bool
    {
        return self::matchesAny($text, self::outboundForbiddenPatterns());
    }

    public static function shouldSkipModel(string $message): bool
    {
        $message = trim($message);

        if ($message === '') {
            return true;
        }

        if (self::matchesAny($message, self::inboundBlockedPatterns())) {
            return true;
        }

        // Короткий трэш без бюджетных слов — отказ без модели.
        if (mb_strlen($message) <= 40 && ! self::mentionsBudget($message) && self::looksLikeAbuseOrNoise($message)) {
            return true;
        }

        return false;
    }

    public static function mentionsBudget(string $text): bool
    {
        return preg_match(
            '/бюджет|расход|доход|лимит|счет|счёт|категор|операц|транзак|долг|цель|остаток|баланс|карт|кошел|перевод|чек|кофе|магазин|зарплат|руб|₽|\d+\s*(р|руб)/iu',
            $text
        ) === 1;
    }

    public static function looksLikeAbuseOrNoise(string $text): bool
    {
        return preg_match(
            '/^(иди|пошёл|пошел|отстань|заткнись|хер|нахер|нахуй|бля|блять|сука|fuck|shit|lol|ахах+|кек)([\s,.!?]|$)/iu',
            trim($text)
        ) === 1;
    }

    /**
     * @return array{text: string, draft: null}
     */
    public static function refusal(): array
    {
        return [
            'text' => AssistantPrompt::offTopicRefusal(),
            'draft' => null,
        ];
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
