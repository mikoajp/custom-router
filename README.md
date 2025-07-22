# Custom Router

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Zaawansowana biblioteka routingu dla aplikacji PHP z obsługą middleware, cache'owania i elastycznej konfiguracji.

## 🚀 Funkcjonalności

- **Zaawansowany routing** - dopasowywanie URL-i z parametrami i wyrażeniami regularnymi
- **Generowanie URL-i** - tworzenie linków na podstawie nazw tras
- **System middleware** - obsługa warstw pośredniczących (CORS, autoryzacja, rate limiting)
- **Cache'owanie tras** - optymalizacja wydajności
- **Konfiguracja YAML** - łatwe zarządzanie trasami
- **Integracja z Symfony** - wykorzystanie komponentów HttpFoundation
- **Logowanie** - szczegółowe logi operacji routingu
- **PSR-3 Logger** - standardowe interfejsy logowania

## 📋 Wymagania

- PHP 8.1 lub wyższy
- Composer

## 📦 Instalacja

```bash
composer require custom/router
```

## 🔧 Podstawowe użycie

### Tworzenie routera

```php
<?php

use Custom\Router\Router;
use Custom\Router\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Tworzenie routera
$router = new Router();

// Dodawanie prostej trasy
$homeRoute = new Route('/', [
    '_controller' => function(Request $request) {
        return new Response('Witaj na stronie głównej!');
    }
]);
$router->addRoute('home', $homeRoute);

// Trasa z parametrem
$userRoute = new Route('/user/{id}', [
    '_controller' => 'App\\Controller\\UserController::show'
], [
    'id' => '\d+' // id musi być liczbą
]);
$router->addRoute('user_show', $userRoute);
```

### Obsługa żądań

```php
$request = Request::createFromGlobals();
$response = $router->handle($request);
$response->send();
```

### Generowanie URL-i

```php
// Generowanie URL-a dla trasy
$url = $router->generateUrl('user_show', ['id' => 123]);
echo $url; // /user/123

// Generowanie absolutnego URL-a
$absoluteUrl = $router->generateUrl('home', [], UrlGenerator::ABSOLUTE_URL);
```

## 🛠️ Zaawansowane funkcje

### Middleware

```php
use Custom\Router\Middleware\CorsMiddleware;
use Custom\Router\Middleware\AuthMiddleware;
use Custom\Router\Middleware\RateLimitMiddleware;

// Dodawanie middleware
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new AuthMiddleware());
$router->addMiddleware(new RateLimitMiddleware(100, 3600)); // 100 żądań na godzinę
```

### Konfiguracja tras w YAML

Utwórz plik `config/routes.yaml`:

```yaml
home:
  path: /
  controller: App\Controller\HomeController::index
  methods: [GET]

user_show:
  path: /user/{id}
  controller: App\Controller\UserController::show
  requirements:
    id: '\d+'
  methods: [GET]

api_users:
  path: /api/users
  controller: App\Controller\ApiController::users
  methods: [GET, POST]
```

Załaduj trasy:

```php
use Custom\Router\Loader\YamlLoader;

$loader = new YamlLoader();
$routes = $loader->load('config/routes.yaml');
$router = new Router($routes);
```

### Trasy z wymaganiami

```php
$route = new Route('/article/{slug}', [
    '_controller' => 'ArticleController::show'
], [
    'slug' => '[a-z0-9-]+' // slug może zawierać tylko małe litery, cyfry i myślniki
]);
```

### Metody HTTP

```php
$route = new Route('/api/users', [
    '_controller' => 'ApiController::users'
], [], [], null, [], ['GET', 'POST']); // tylko GET i POST
```

## 🏗️ Architektura

### Główne komponenty

- **Router** - główna klasa zarządzająca routingiem
- **Route** - reprezentacja pojedynczej trasy
- **RouteCollection** - kolekcja tras
- **UrlMatcher** - dopasowywanie URL-i do tras
- **UrlGenerator** - generowanie URL-i
- **MiddlewarePipeline** - system middleware

### Interfejsy

- `RouterInterface` - kontrakt dla routera
- `RouteInterface` - kontrakt dla trasy
- `UrlMatcherInterface` - kontrakt dla matchera
- `UrlGeneratorInterface` - kontrakt dla generatora
- `MiddlewareInterface` - kontrakt dla middleware

## 🔍 Przykłady użycia

### Kontroler jako klasa

```php
class UserController
{
    public function show(Request $request, string $id): Response
    {
        return new Response("Użytkownik ID: $id");
    }
}

$route = new Route('/user/{id}', [
    '_controller' => 'UserController::show'
]);
```

### API REST

```php
// GET /api/users
$router->addRoute('api_users_list', new Route('/api/users', [
    '_controller' => 'ApiController::listUsers'
], [], [], null, [], ['GET']));

// POST /api/users
$router->addRoute('api_users_create', new Route('/api/users', [
    '_controller' => 'ApiController::createUser'
], [], [], null, [], ['POST']));

// GET /api/users/{id}
$router->addRoute('api_users_show', new Route('/api/users/{id}', [
    '_controller' => 'ApiController::showUser'
], ['id' => '\d+'], [], null, [], ['GET']));
```

## 🧪 Testowanie

```bash
# Uruchomienie testów
composer test

# Testy z pokryciem kodu
composer test-coverage
```

## 📝 Logowanie

Router automatycznie loguje wszystkie operacje:

```php
// Logi są zapisywane w logs/router.log
// Zawierają informacje o:
// - Dopasowanych trasach
// - Parametrach żądań
// - Błędach routingu
// - Czasach wykonania
```

## 🤝 Wkład w rozwój

1. Fork projektu
2. Utwórz branch dla nowej funkcjonalności (`git checkout -b feature/amazing-feature`)
3. Commit zmian (`git commit -m 'Add amazing feature'`)
4. Push do brancha (`git push origin feature/amazing-feature`)
5. Otwórz Pull Request

## 📄 Licencja

Ten projekt jest licencjonowany na licencji MIT - zobacz plik [LICENSE](LICENSE) dla szczegółów.

## 👨‍💻 Autor

**Mikołaj Przybysz**

## 🆘 Wsparcie

Jeśli masz pytania lub problemy, utwórz [issue](../../issues) w repozytorium.

## 📚 Dokumentacja

Więcej informacji znajdziesz w [dokumentacji](docs/).

---

⭐ **Jeśli ten projekt Ci pomógł, zostaw gwiazdkę!** ⭐