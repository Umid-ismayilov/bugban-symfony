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
            // Console commands never reach kernel.exception; without this a
            // failing cron/bin/console run was invisible to Bugban.
            'console.error' => 'onConsoleError',
            // Per-run attribution (which command/cron loaded the server): the
            // core records one run per process; these give it the command name
            // and exit code instead of a raw argv guess.
            'console.command' => 'onConsoleCommand',
            'console.terminate' => 'onConsoleTerminate',
            // Messenger workers process many messages in one long process;
            // each message becomes its own run (like a queue job in Laravel).
            // Subscribing to an event nobody dispatches is harmless, so this
            // is safe when symfony/messenger is not installed.
            'Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent' => 'onMessageReceived',
            'Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent' => 'onMessageHandled',
            'Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent' => 'onMessageFailed',
        );
    }

    /**
     * @param object $event Symfony\Component\Console\Event\ConsoleCommandEvent
     */
    public function onConsoleCommand($event)
    {
        try {
            if (!method_exists('Bugban\\Sdk\\Bugban', 'setCommand')) {
                return;
            }
            $cmd = method_exists($event, 'getCommand') ? $event->getCommand() : null;
            $name = ($cmd && method_exists($cmd, 'getName')) ? (string) $cmd->getName() : '';
            if ($name !== '') {
                Bugban::setCommand($name);
            }
            if ($name === 'messenger:consume' && method_exists('Bugban\\Sdk\\Bugban', 'setRunSource')) {
                Bugban::setRunSource('queue');
            }
        } catch (\Exception $e) {
            // monitoring never breaks the host
        } catch (\Throwable $e) {
            // same for engine errors
        }
    }

    /**
     * @param object $event Symfony\Component\Console\Event\ConsoleTerminateEvent
     */
    public function onConsoleTerminate($event)
    {
        try {
            if (method_exists('Bugban\\Sdk\\Bugban', 'setExitCode') && method_exists($event, 'getExitCode')) {
                Bugban::setExitCode((int) $event->getExitCode());
            }
        } catch (\Exception $e) {
            // ignore
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param object $event WorkerMessageReceivedEvent
     */
    public function onMessageReceived($event)
    {
        try {
            if (!method_exists('Bugban\\Sdk\\Bugban', 'beginJob')) {
                return;
            }
            $class = self::messageClass($event);
            if ($class !== '') {
                $meta = array('kind' => 'messenger');
                if (method_exists($event, 'getReceiverName')) {
                    $meta['queue'] = (string) $event->getReceiverName();
                }
                Bugban::beginJob($class, $meta);
            }
        } catch (\Exception $e) {
            // ignore
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param object $event WorkerMessageHandledEvent
     */
    public function onMessageHandled($event)
    {
        try {
            if (method_exists('Bugban\\Sdk\\Bugban', 'endJob')) {
                Bugban::endJob(0);
            }
        } catch (\Exception $e) {
            // ignore
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param object $event WorkerMessageFailedEvent
     */
    public function onMessageFailed($event)
    {
        try {
            if (!method_exists('Bugban\\Sdk\\Bugban', 'endJob')) {
                return;
            }
            $err = null;
            if (method_exists($event, 'getThrowable')) {
                $t = $event->getThrowable();
                if ($t) {
                    $err = get_class($t) . ': ' . $t->getMessage();
                }
            }
            Bugban::endJob(1, $err);
        } catch (\Exception $e) {
            // ignore
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param object $event
     * @return string
     */
    private static function messageClass($event)
    {
        if (!method_exists($event, 'getEnvelope')) {
            return '';
        }
        $env = $event->getEnvelope();
        if ($env && method_exists($env, 'getMessage')) {
            $msg = $env->getMessage();
            return is_object($msg) ? get_class($msg) : '';
        }
        return '';
    }

    /**
     * @param object $event Symfony\Component\Console\Event\ConsoleErrorEvent
     */
    public function onConsoleError($event)
    {
        $error = null;
        if (method_exists($event, 'getError')) {
            $error = $event->getError();
        } elseif (method_exists($event, 'getException')) {
            $error = $event->getException();
        }
        if ($error) {
            self::reportUnhandled($error);
        }
    }

    /**
     * An exception that reached Symfony's kernel/console error path is UNHANDLED
     * (Bugsnag semantics). Guarded against an older core lacking the method.
     *
     * @param \Throwable|\Exception $e
     */
    private static function reportUnhandled($e)
    {
        if (method_exists('Bugban\\Sdk\\Bugban', 'captureUnhandled')) {
            Bugban::captureUnhandled($e);
        } else {
            Bugban::capture($e);
        }
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
            self::reportUnhandled($throwable);
        }
    }
}
