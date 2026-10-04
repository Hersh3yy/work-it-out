<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use RuntimeException;

/**
 * Telegram could not be reached or refused a call.
 *
 * Carries only the method, HTTP status and Telegram's description: never the
 * request URL, which contains the bot token.
 */
final class TelegramUnavailable extends RuntimeException {}
