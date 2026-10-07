<?php

/**
 * TForumException class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Exceptions;

use Prado\Exceptions\TException;

/**
 * TForumException is the base exception class for all forum-specific exceptions.
 *
 * It overrides {@link translateErrorMessage()} to load the human-readable message
 * text from the extension's own {@link errorMessages.txt} file located in the same
 * directory as this class (`src/Exceptions/`), rather than from the PRADO framework's
 * core message file.
 *
 * ### Error key format
 *
 * Keys are lowercase, underscore-separated strings prefixed by the subsystem:
 * ```
 * forum_manager_invalid_connection_id
 * forum_control_manager_not_found
 * forum_newthread_spam_detected
 * ```
 *
 * ### Placeholder format
 *
 * Use `%s` (or `%d` / `%f`) sprintf-style placeholders. Pass additional arguments
 * after the key:
 * ```php
 * throw new TForumConfigurationException('forum_invalid_connection_id', $moduleId);
 * ```
 *
 * Messages in `errorMessages.txt` use `{0}`, `{1}` … for documentation clarity, but
 * the actual runtime substitution uses sprintf-style ordering matching func_get_args().
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumException extends TException
{
    /**
     * Loads the message string for $msg from the extension's errorMessages.txt.
     *
     * Falls back to the raw key string if no entry is found, so keys are always
     * identifiable in logs even without a matching message entry.
     *
     * @param string $msg error key (case-insensitive)
     * @return string human-readable message template
     */
    protected function translateErrorMessage($msg)
    {
        static $messages = null;

        if ($messages === null) {
            $messages = [];
            $msgFile  = __DIR__ . '/errorMessages.txt';
            $lines    = @file($msgFile);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Skip blank lines and comment lines (starting with ;)
                    if ($line === '' || $line[0] === ';') {
                        continue;
                    }
                    $pos = strpos($line, '=');
                    if ($pos !== false) {
                        $key            = strtolower(rtrim(substr($line, 0, $pos)));
                        $messages[$key] = ltrim(substr($line, $pos + 1));
                    }
                }
            }
        }

        $key = strtolower($msg);
        return $messages[$key] ?? $msg;
    }
}


/**
 * TForumConfigurationException represents errors in TForumManager / TForumPlugin
 * configuration — e.g. invalid property values, missing modules, bad ConnectionID.
 */
class TForumConfigurationException extends TForumException
{
}


/**
 * TForumInvalidOperationException represents attempts to perform operations that are
 * not permitted in the current state — e.g. posting to a locked thread, voting twice
 * in a poll, or writing to a non-writable directory.
 */
class TForumInvalidOperationException extends TForumException
{
}


/**
 * TForumInvalidDataValueException represents invalid data supplied by caller code
 * or HTTP input — e.g. a negative page number, an empty required field, or an
 * out-of-range spam score threshold.
 */
class TForumInvalidDataValueException extends TForumException
{
}


/**
 * TForumSystemException represents unrecoverable system-level errors — e.g. a
 * failed database save, a missing schema table, or a filesystem error.
 */
class TForumSystemException extends TForumException
{
}


/**
 * TForumAccessDeniedException is thrown when a user attempts an action they are not
 * authorised to perform — e.g. accessing the admin panel without the ForumAdmin role.
 */
class TForumAccessDeniedException extends TForumException
{
}
