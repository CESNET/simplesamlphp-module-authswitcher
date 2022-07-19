<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

class ContextSettings
{
    public static function parse_config($config)
    {
        $contexts_regex = $config->getBoolean('contexts_regex', false);
        $password_contexts = $config->getArray('password_contexts', AuthSwitcher::PASSWORD_CONTEXTS);
        $mfa_contexts = $config->getArray('mfa_contexts', AuthSwitcher::MFA_CONTEXTS);
        if ($contexts_regex) {
            $password_contexts_patterns = array_filter(self::is_regex, $password_contexts);
            $password_contexts = array_diff($password_contexts, $password_contexts_patterns);
            $mfa_contexts_patterns = array_filter(self::is_regex, $mfa_contexts);
            $mfa_contexts = array_diff($mfa_contexts, $mfa_contexts_patterns);
        } else {
            $password_contexts_patterns = [];
            $mfa_contexts_patterns = [];
        }

        return [$password_contexts, $mfa_contexts, $password_contexts_patterns, $mfa_contexts_patterns];
    }

    private static function is_regex($str)
    {
        return strlen($str) > 2 && substr($str, 0, 1) === '/' && substr($str, -1) === '/';
    }
}
