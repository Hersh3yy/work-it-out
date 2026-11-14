<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The person blocked the bot or closed the chat. Sending again will not help,
 * so the caller stops sending on its own until they write again.
 */
final class RecipientUnreachable extends ChatUnavailable {}
