<?php

namespace Event;

use Handler;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;

/**
 * Class ResponseEvent
 * Each time the framework handles a Request, a ResponseEvent event is now dispatched.
 */
class ResponseEvent extends EventDispatcher\Event
{
    /** @var HttpFoundation\Request */
    private $request;

    /** @var HttpFoundation\Response */
    private $response;

    /**
     * ResponseEvent constructor.
     *
     * @param HttpFoundation\Response $response
     * @param HttpFoundation\Request  $request
     */
    public function __construct(HttpFoundation\Response $response, HttpFoundation\Request $request)
    {
        $this->response = $response;
        $this->request = $request;
        $this->handleSession('/app/cache/session');
    }

    /**
     * @return HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param string $savePath
     * @param int    $expireTime
     */
    private function handleSession($savePath, $expireTime = 1)
    {
        $sessionHandler = new Handler\Session\SessionHandler($savePath, $expireTime);
        if ($sessionHandler->startSession()) {
            $session = $sessionHandler->getSession();
            $isError = $this->response->isRedirect() || $this->response->getContent() === '';
            if ($isError && $this->response->headers->has('ErrorData')) {
                $session->set('ErrorData', $this->response->headers->get('ErrorData'));
            } elseif (time() - $session->getMetadataBag()->getLastUsed() > $expireTime || $sessionHandler->hasError()) {
                $session->invalidate();   //throw new SessionExpired; // redirect to expired session page
            }
            $this->request->setSession($session);
        }
    }
}
