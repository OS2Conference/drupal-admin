<?php

namespace Drupal\os2conticki_app\Controller;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\os2conticki_content\Helper\ConferenceHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Conference controller.
 */
class ConferenceController extends ControllerBase implements ContainerInjectionInterface {
  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The app library.
   *
   * @var string
   */
  private $library = 'os2conticki_app/display-react';

  /**
   * The IE 11 polyfills library.
   *
   * @var string
   */
  private $libraryIE11 = 'os2conticki_app/display-react-ie11';

  /**
   * The conference helper.
   *
   * @var \Drupal\os2conticki_content\Helper\ConferenceHelper
   */
  private $conferenceHelper;

  /**
   * Constructor.
   */
  public function __construct(RendererInterface $renderer, RequestStack $requestStack, ConferenceHelper $conferenceHelper) {
    $this->renderer = $renderer;
    $this->requestStack = $requestStack;
    $this->conferenceHelper = $conferenceHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('os2conticki_content.conference_helper')
    );
  }

  /**
   * Render conference app.
   */
  public function app(NodeInterface $node, string $type = NULL, string $uuid = NULL) {
    // Redirect to conference path if node is not a conference.
    if (('conference' !== $node->bundle()) && $node->hasField('field_conference')) {
      $list = $node->get('field_conference')->referencedEntities();
      $conference = reset($list);
      return $this->redirect('os2conticki_app.conference_app', [
        'node' => $conference->id(),
        'type' => $node->bundle(),
        'entity' => $node->uuid(),
      ]);
    }

    if ('conference' !== $node->bundle()) {
      throw new NotFoundHttpException();
    }

    $basename = $this->getBasename($node);
    $apiUrl = $this->getApiUrl($node);
    $appData = $this->getAppData($apiUrl);
    $icons = $this->getIcons($appData);
    $applicationName = $node->getTitle();

    $config = $this->config('os2conticki_app.settings');

    $manifestUrl = $this->getManifestUrl($node);
    $serviceWorkerUrl = $this->getServiceWorkerUrl($node);
    $serviceWorkerParameters = (object) [];

    $tracking = $this->renderTracking($node);

    $renderable = [
      '#theme' => 'os2conticki_app_app',
      '#conference' => $node,
      '#basename' => $basename,
      '#manifest_url' => $manifestUrl,
      '#icons' => $icons,
      '#application_name' => $applicationName,
      '#app_data' => $appData,
      '#api_url' => $apiUrl,
      '#app_stylesheets' => $this->getCssLibraryElements($this->library),
      '#app_scripts' => $this->getJsLibraryElements($this->library),
      '#app_scripts_ie11' => $this->getJsLibraryElements($this->libraryIE11),
      '#service_worker_url' => $serviceWorkerUrl,
      '#service_worker_parameters' => $serviceWorkerParameters,
      '#tracking' => $tracking,
      // @see https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays
      '#cache' => [
        'contexts' => [
          'url.query_args:preview',
        ],
      ],
    ];

    $content = $this->renderer->renderPlain($renderable);

    return $this->cacheResponse(new CacheableResponse($content), $node);
  }

  /**
   * Get style link elements for Drupal libraries.
   */
  private function getCssLibraryElements($libraries) {
    $libraries = (array) $libraries;
    $assets = AttachedAssets::createFromRenderArray([
      '#attached' => ['library' => $libraries],
    ]);
    $optimize = FALSE;
    /** @var \Drupal\Core\Asset\CssCollectionRenderer $cssRenderer */
    $cssRenderer = \Drupal::service('asset.css.collection_renderer');
    /** @var \Drupal\Core\Asset\AssetResolverInterface $resolver */
    $resolver = \Drupal::service('asset.resolver');
    $assets = $resolver->getCssAssets($assets, $optimize);

    // We need absolute asset urls to support custom app urls.
    return $this->makeAbsolute($cssRenderer->render($assets));
  }

  /**
   * Get script elements for Drupal libraries.
   */
  private function getJsLibraryElements($libraries) {
    $libraries = (array) $libraries;
    $assets = AttachedAssets::createFromRenderArray([
      '#attached' => ['library' => $libraries],
    ]);
    $optimize = FALSE;
    /** @var \Drupal\Core\Asset\JsCollectionRenderer $jsRenderer */
    $jsRenderer = \Drupal::service('asset.js.collection_renderer');
    /** @var \Drupal\Core\Asset\AssetResolverInterface $resolver */
    $resolver = \Drupal::service('asset.resolver');

    // @TODO Find a better and more obvious way to do this.
    $assets = $resolver->getJsAssets($assets, $optimize);
    $assets = array_values(array_filter($assets));

    return array_map(function ($asset) use ($jsRenderer, $resolver, $optimize) {
      // We need absolute asset urls to support custom app urls.
      return $this->makeAbsolute($jsRenderer->render($asset));
    }, $assets);
  }

  /**
   * Make asset referenes absolute.
   */
  private function makeAbsolute(array $renderable) {
    foreach ($renderable as &$item) {
      if (isset($item['#attributes']['href'])) {
        $item['#attributes']['href'] = Url::fromUri('base:/', ['absolute' => TRUE])->toString() . ltrim($item['#attributes']['href'], '/');
      }
      if (isset($item['#attributes']['src'])) {
        $item['#attributes']['src'] = Url::fromUri('base:/', ['absolute' => TRUE])->toString() . ltrim($item['#attributes']['src'], '/');
      }
    }

    return $renderable;
  }

  /**
   * Render app tracking code.
   */
  private function renderTracking(NodeInterface $node) {
    $renderable = NULL;

    if ($key = trim($node->get('field_siteimprove_key')->value)) {
      $renderable = [
        '#theme' => 'os2conticki_app_tracking_siteimprove',
        '#key' => $key,
      ];
    }

    return $renderable ? $this->renderer->renderPlain($renderable) : NULL;
  }

  /**
   * Render app manifest.
   */
  public function manifest(NodeInterface $node) {
    if ('conference' !== $node->bundle()) {
      throw new NotFoundHttpException();
    }

    $manifest = [];

    $apiUrl = $this->getApiUrl($node);
    $data = $this->getConferenceData($apiUrl);
    if ($data) {
      $manifest = [
        'short_name' => $data['title'],
        'name' => $data['title'],
        'start_url' => $this->getAppUrl($node),
        'theme_color' => $data['app']['primary_color'] ?? '#1E3284',
        'background_color' => '#1E3284',
        'display' => 'standalone',
      ];
      if (isset($data['app']['icons'])) {
        $icons = [];
        foreach ($data['app']['icons'] as $size => $url) {
          $parts = parse_url($url);
          $extension = pathinfo($parts['path'], PATHINFO_EXTENSION);
          $type = 'image/' . $extension;
          $icons[] = array_filter([
            'src' => $url,
            'sizes' => $size,
            'type' => $type,
            'purpose' => '128x128' === $size ? 'any maskable' : NULL,
          ]);
        }
        $manifest['icons'] = $icons;
      }
    }

    return $this->cacheResponse(new CacheableJsonResponse($manifest), $node);
  }

  /**
   * Render app service worker.
   */
  public function serviceWorker(NodeInterface $node) {
    if ('conference' !== $node->bundle()) {
      throw new NotFoundHttpException();
    }

    [$extension, $name] = explode('/', $this->library);
    $library = \Drupal::service('library.discovery')
      ->getLibraryByName($extension, $name);
    $preCacheKey = 'os2conticki-app-cache-' . $library['version'];

    $preCacheUrls = [
      $this->getAppUrl($node),
      $this->getManifestUrl($node),
    ];
    $assets = $this->getCssLibraryElements($this->library);
    foreach ($this->getJsLibraryElements($this->library) as $asset) {
      $assets = array_merge($assets, $asset);
    }
    foreach ($assets as $item) {
      if (isset($item['#attributes']['href'])) {
        $preCacheUrls[] = $item['#attributes']['href'];
      }
      if (isset($item['#attributes']['src'])) {
        $preCacheUrls[] = $item['#attributes']['src'];
      }
    }

    $renderable = [
      '#theme' => 'os2conticki_app_service_worker',
      '#precache_key' => $preCacheKey,
      '#precache_urls' => $preCacheUrls,
    ];

    $content = $this->renderer->renderPlain($renderable);
    // Remove HTML comments (e.g. template suggestions).
    $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

    $response = new CacheableResponse($content);
    $response->headers->set('content-type', 'text/javascript');

    return $this->cacheResponse($response, $node);
  }

  /**
   * Cache a response.
   */
  private function cacheResponse(CacheableResponseInterface $response, NodeInterface $node) {
    $cacheMetadata = new CacheableMetadata();
    // Invalidate cache when the conference node changes.
    // @see https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags
    $cacheMetadata
      ->addCacheTags(['node:' . $node->id()])
      ->addCacheContexts(['url.query_args:preview']);
    $response->addCacheableDependency($cacheMetadata);

    return $response;
  }

  /**
   * Get app url.
   */
  private function getAppUrl(NodeInterface $node, string $path = NULL): string {
    $request = $this->requestStack->getCurrentRequest();
    $preview = $request->get('preview');

    if ($preview || !isset($node->field_custom_app_url->uri)) {
      $route = 'os2conticki_app.conference_app';
      if (NULL !== $path) {
        $route .= '_' . str_replace('-', '_', $path);
      }

      return $this->generateUrl($route, array_filter([
        'node' => $node->id(),
        'preview' => $preview,
      ]), [
        'absolute' => TRUE,
      ]);
    }

    $appUrl = $node->field_custom_app_url->uri;
    if (NULL !== $path) {
      $appUrl = rtrim($appUrl, '/') . '/' . ltrim($path, '/');
      // Add the conference id to the manifest url to make the service worker
      // update if a custom url is changed to point to another conference.
      if ('manifest' === $path) {
        $appUrl .= (FALSE === strpos($appUrl, '?') ? '?' : '&') . 'id=' . $node->id();
      }
    }

    return $appUrl;
  }

  /**
   * Get manifest url.
   */
  private function getManifestUrl(NodeInterface $node): string {
    return $this->getAppUrl($node, 'manifest');
  }

  /**
   * Get service worker url.
   */
  private function getServiceWorkerUrl(NodeInterface $node): string {
    return $this->getAppUrl($node, 'service-worker');
  }

  /**
   * Get app basename.
   */
  private function getBasename(NodeInterface $node): string {
    $appUrl = $this->getAppUrl($node);
    $parts = parse_url($appUrl);

    $baseName = $parts['path'] ?? '/';
    if (isset($parts['query'])) {
      $baseName .= '?' . $parts['query'];
    }

    return $baseName;
  }

  /**
   * Get conference api url.
   */
  private function getApiUrl(NodeInterface $node): string {
    return $this->conferenceHelper->getApiUrl($node);
  }

  /**
   * Generate a url.
   */
  private function generateUrl(string $route, array $parameters = [], array $options = []): string {
    $options += [
      // We want to get content in the default language.
      'language' => \Drupal::service('language_manager')->getDefaultLanguage(),
    ];

    return Url::fromRoute($route, $parameters, $options)
      // @see https://www.lullabot.com/articles/early-rendering-a-lesson-in-debugging-drupal-8
      ->toString(TRUE)
      ->getGeneratedUrl();
  }

  /**
   * Get data from api.
   */
  private function getConferenceData(string $apiUrl) {
    try {
      $client = \Drupal::httpClient();
      $response = $client->get($apiUrl);

      $data = json_decode($response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);

      return $data['data']['attributes'] ?? NULL;
    }
    catch (\Exception $exception) {
      return NULL;
    }
  }

  /**
   * Get app data from api.
   */
  private function getAppData(string $apiUrl) {
    $data = $this->getConferenceData($apiUrl);

    return $data['app'] ?? NULL;
  }

  /**
   * Get app icons.
   */
  private function getIcons(array $appData = NULL): array {
    $icons = [];
    $appIcons = $appData['icons'] ?? NULL;
    if ($appIcons) {
      $addIcons = function ($rel, $sizes, $tagName = 'link') use ($appIcons, &$icons) {
        foreach ($sizes as $size) {
          if (isset($appIcons[$size])) {
            $icons[] = [
              'tagName' => $tagName,
              'attributes' => [
                'rel' => $rel,
                'sizes' => $size,
                'href' => $appIcons[$size],
              ],
            ];
          }
        }
      };

      // iOS icons.
      $rel = 'apple-touch-icon';
      $sizes = [
        "152x152",
        "144x144",
        "120x120",
        "114x114",
        "76x76",
        "72x72",
        "60x60",
        "57x57",
      ];
      $addIcons($rel, $sizes);

      // iOS launch screens.
      $rel = 'apple-touch-startup-image';
      $sizes = [
        '2048x2732',
        '1668x2224',
        '1536x2048',
        '1125x2436',
        '1242x2208',
        '750x1334',
        '640x1136',
      ];
      $addIcons($rel, $sizes);

      // Android icons - for legacy devices, newer versions gets the icons
      // from the webmanifest file.
      $rel = 'icon';
      $sizes = [
        '196x196',
        '96x96',
        '32x32',
        '16x16',
        '128x128',
      ];
      $addIcons($rel, $sizes);

      // MS icons.
      $metas = [
        'msapplication-square150x150logo' => '150x150',
        'msapplication-wide310x150logo' => '310x150',
        'msapplication-square310x310logo' => '310x310',
        'msapplication-square70x70logo' => '70x70',
      ];
      foreach ($metas as $name => $size) {
        if (isset($appIcons[$size])) {
          $icons[] = [
            'tagName' => 'meta',
            'attributes' => [
              'name' => $name,
              'content' => $appIcons[$size],
            ],
          ];
        }
      }
    }

    return $icons;
  }

  /**
   * Render app info.
   */
  public function appInfo(NodeInterface $node) {
    if (('conference' !== $node->bundle()) && $node->hasField('field_conference')) {
      $list = $node->get('field_conference')->referencedEntities();
      $node = reset($list);
    }

    if (!$node || 'conference' !== $node->bundle()) {
      throw new NotFoundHttpException();
    }

    $apiUrl = $this->conferenceHelper->getApiUrl($node);
    $appUrl = $this->conferenceHelper->getAppUrl($node);
    $appUrlPreview = $this->conferenceHelper->getAppUrl($node, ['preview' => TRUE]);

    return [
      '#theme' => 'os2conticki_content_conference_info',
      '#conference' => $node,
      '#api_url' => $apiUrl,
      '#app_url' => $appUrl,
      '#app_url_preview' => $appUrlPreview,
      '#weight' => -1000,
    ];
  }

}
