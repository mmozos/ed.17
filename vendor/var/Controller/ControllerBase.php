<?php

namespace Controller;

use Controller;
use Handler;
use Helper;
use Symfony\Component\HttpFoundation;
use Twig;

/**
 * Class ControllerBase.
 */
class ControllerBase
{
    use Controller\ControllerTrait;

    /**
     * ControllerBase constructor.
     */
    public function __construct()
    {
        /* @var array codes */
        $this->codes = \def::dbCodes();
        /* @var array langISOCodes */
        $this->langISOCodes = \def::langISOCodes();
        /* @var array metric */
        $this->metric = \def::metric();
        /* @var array targets */
        $this->targets = \def::dbTargets();
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    private static function isLocalAdmin(array $args)
    {
        return isset($args['studentCode']) && $args['studentCode'] === \defDb::adminUsername() && isset($args['studentPassword']) && $args['studentPassword'] === \defDb::adminPassword();
    }

    /**
     * @param array $args
     *
     * @return array
     */
    private static function authorize(array $args)
    {
        $path = static::isLocalAdmin($args) ? str_replace('%kernel.root_dir%', getenv('ROOT_DIR').'/app', \defDb::dbLocal()['path']) : false;
        if ($path && $path = realpath($path)) {
            return array_merge($args, \defDb::dbLocal(), ['path' => $path]);
        }

        return array_merge($args, \defDb::dbDist());
    }

    /**
     * Generates a response from the given content.
     *
     * @param string $content
     * @param int    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    private static function processView($content = 'Hello World!', $expiryMinutes = 1)
    {
        /** @var HttpFoundation\Response $response */
        $response = new HttpFoundation\Response($content, 200);
        // avoid one of the most widespread Internet security issue, XSS (Cross-Site Scripting)
        $response->headers->set('Content-Type', 'text/html');
        // compression
        $response->headers->set('Accept-Encoding', 'gzip, deflate');
        // configure the HTTP cache headers
        $response->setMaxAge($expiryMinutes * 60);
        // return response object back
        return $response;
    }

    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param int                    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    public function renderAction(HttpFoundation\Request $request, $expiryMinutes = 1)
    {
        if ($request->isXmlHttpRequest() && 'POST' === $request->getMethod() && $user = $request->request->get('user')) {
            /** @var string $clientIp */
            $clientIp = $request->getClientIp();
            /** @var Handler\Uploader $doc */
            $doc = new Handler\Uploader();
            /** @var int $time */
            $time = time();
            /** @var string $token */
            $token = uniqid();
            /** @var HttpFoundation\File\UploadedFile $file */
            foreach ($request->files as $file) {
                $doc->setFile($file);
                $result = $doc->processFile($clientIp, $user, $time, $token);
                if (!is_numeric($result)) {
                    $doc->processFile($clientIp, $user, $time, $token = $result);
                }
            }

            return HttpFoundation\JsonResponse::create($doc->doPurge());
        }
        $data = $this->getData($request);
        $route = $request->get('_route');
        if ('boarding' === $route && (false !== strpos($data['code'], 'simul') || false !== strpos($data['code'], 'dbh417') || false !== strpos($data['code'], 'lh617'))) {
            $route = 'onboard';
            $messages = Helper\TranslationsHelper::localize(parseConfig(getenv('TRANSLATIONS_DIR').'/page', $route), $data, $this->langISOCodes);
            $request = HttpFoundation\Request::create(null, $request->getMethod(), array_merge($request->request->all(), $messages/*, ['flabel' => null]*/));
            $data = $this->getSplitPageData($request);
        } elseif ('boarding' === $route && !isset($data['code'])) {
            $route = 'upload';
            $messages = Helper\TranslationsHelper::localize(parseConfig(getenv('TRANSLATIONS_DIR').'/page', $route), [], $this->langISOCodes);
            $data = array_merge($data, $messages);
        }
        $view = Twig\TwigHandler::render($route, $data);

        return static::processView($view, $expiryMinutes);
    }

    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param string|int             $page
     * @param int                    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    public function pageRenderAction(HttpFoundation\Request $request, $page = 0, $expiryMinutes = 1)
    {
        $data = $this->getSplitPageData($request, $page);
        $view = Twig\TwigHandler::render($request->get('_route'), $data);

        return static::processView($view, $expiryMinutes);
    }
}
