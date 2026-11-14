<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A chat provider did not take a message the app sent on its own (a retried
 * reply, the morning message). Thrown by ChatChannel adapters so the core
 * never sees a provider's own error.
 */
class ChatUnavailable extends RuntimeException {}
