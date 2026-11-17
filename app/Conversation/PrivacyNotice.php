<?php

declare(strict_types=1);

namespace App\Conversation;

/**
 * What a person is told once, when their account is connected: what is
 * stored, who can see it, where the text goes and how to have it deleted.
 */
final class PrivacyNotice
{
    public const string TEXT = "Before you start: Feetness stores what you send here, so your workouts, body weight, injuries and how they are going, your goals and the coach's answers. Hiren runs Feetness and can see all of it. Your messages are sent to an AI model to read them and to answer you. Nothing is sold or shared. Want it all gone? Tell Hiren and your account and everything in it is deleted.";
}
