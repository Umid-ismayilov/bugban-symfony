<?php

namespace Bugban\Symfony\EventListener;

use Bugban\Sdk\Bugban;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExceptionListener implements EventSubscriberInterface
{
    /**
     * Use the string event name ("kernel.exception") for cross-version safety,
     * since the KernelEvents constant resolves to the same string in every
     * supported Symfony version.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'kernel.exception' => 'onKernelException',
        );
    }

    /**
     * @param object $event
     */
    public function onKernelException($event)
    {
        // Symfony >= 4.4 uses getThrowable(); older versions use getException().
        if (method_exists($event, 'getThrowable')) {
            $throwable = $event->getThrowable();
        } else {
            $throwable = $event->getException();
        }

        if ($throwable) {
            Bugban::capture($throwable);
        }
    }
}
