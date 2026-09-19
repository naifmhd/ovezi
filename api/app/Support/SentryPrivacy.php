<?php

namespace App\Support;

use Sentry\Event;
use Sentry\UserDataBag;

class SentryPrivacy
{
    public static function scrub(Event $event): Event
    {
        $requestMethod = $event->getRequest()['method'] ?? null;
        $event->setRequest(is_string($requestMethod) ? ['method' => $requestMethod] : []);
        $event->setExtra([]);

        $userId = $event->getUser()?->getId();
        $event->setUser($userId === null ? null : UserDataBag::createFromUserIdentifier($userId));

        return $event;
    }
}
