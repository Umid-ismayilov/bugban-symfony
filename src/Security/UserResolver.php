<?php

namespace Bugban\Symfony\Security;

/**
 * Finds the logged-in user from Symfony Security so events carry it without a
 * manual Bugban::setUser(). The token storage holds the token of whichever
 * firewall matched this request (admin, api, main...), so every firewall is
 * covered. Never throws; null when security is not installed or nobody is
 * logged in.
 */
class UserResolver
{
    /** @var object|null Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
    private $tokenStorage;

    /**
     * @param object|null $tokenStorage
     */
    public function __construct($tokenStorage = null)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return array|null
     */
    public function __invoke()
    {
        try {
            if (!is_object($this->tokenStorage) || !method_exists($this->tokenStorage, 'getToken')) {
                return null;
            }
            $token = $this->tokenStorage->getToken();
            if (!is_object($token) || !method_exists($token, 'getUser')) {
                return null;
            }
            $user = $token->getUser();
            // Symfony < 6 used the string "anon." for anonymous visitors.
            if (!is_object($user)) {
                return null;
            }

            $identifier = null;
            if (method_exists($user, 'getUserIdentifier')) {
                $identifier = $user->getUserIdentifier();
            } elseif (method_exists($user, 'getUsername')) {
                $identifier = $user->getUsername();
            }
            $id = method_exists($user, 'getId') ? $user->getId() : null;
            $email = method_exists($user, 'getEmail') ? $user->getEmail() : null;
            $name = null;
            foreach (array('getFullName', 'getName', 'getDisplayName') as $m) {
                if (method_exists($user, $m)) {
                    $name = $user->$m();
                    break;
                }
            }
            if ($email === null && is_string($identifier) && strpos($identifier, '@') !== false) {
                $email = $identifier;
            }
            if ($name === null && $identifier !== null && $identifier !== $email) {
                $name = $identifier;
            }

            $firewall = method_exists($token, 'getFirewallName') ? $token->getFirewallName()
                : (method_exists($token, 'getProviderKey') ? $token->getProviderKey() : null);

            return array(
                'id' => is_scalar($id) ? $id : (is_scalar($identifier) ? $identifier : null),
                'email' => is_scalar($email) ? $email : null,
                'name' => is_scalar($name) ? $name : null,
                'guard' => is_scalar($firewall) ? $firewall : null,
            );
        } catch (\Exception $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
