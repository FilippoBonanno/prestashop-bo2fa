<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/* Pagine Symfony del BO. Priorità -10: dopo router (32), firewall (8), sessione employee PS9 (7) e contesto legacy (0). */
class Bo2faAdminRequestSubscriber implements EventSubscriberInterface
{
    const OPEN_ROUTES = ['admin_login', 'admin_logout'];

    public static function getSubscribedEvents()
    {
        return ['kernel.request' => ['onKernelRequest', -10]];
    }

    public function onKernelRequest($event)
    {
        $isMain = method_exists($event, 'isMainRequest') ? $event->isMainRequest() : $event->isMasterRequest();
        if (!$isMain || !Module::isEnabled('bo2fa')) {
            return;
        }
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if ($route === '' || $route[0] === '_' || in_array($route, self::OPEN_ROUTES, true)) {
            return;
        }
        // Mai il parametro "controller" della query: su una rotta Symfony qualsiasi basterebbe ?controller=AdminLogin per saltare il gate.
        // PS 9 instrada le pagine legacy su admin_legacy_controller_route e mette la classe istanziata in _legacy_controller_class.
        $class = $request->attributes->get('_legacy_controller_class');
        $legacy = $request->attributes->get('_legacy_controller');
        if (is_string($class) && $class !== '') {
            $controller = preg_replace('/Controller(Override)?$/', '', $class);
        } elseif (is_string($legacy) && $legacy !== '') {
            $controller = $legacy;
        } else {
            $controller = $route;
        }
        $target = Bo2faGate::decide($controller);
        if ($target === null) {
            return;
        }
        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse(['error' => '2fa_required'], 403));

            return;
        }
        $event->setResponse(new RedirectResponse(Bo2faGate::redirectUrl($target)));
    }
}
