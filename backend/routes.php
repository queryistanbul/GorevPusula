<?php
/**
 * Route Definitions
 * 
 * Maps HTTP methods and paths to controller actions
 */

class Router
{
    private $routes = [];

    /**
     * Add a route
     */
    public function addRoute($method, $path, $controller, $action)
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    /**
     * Dispatch request to appropriate controller
     */
    public function dispatch($method, $uri)
    {
        // Remove query string
        $uri = parse_url($uri, PHP_URL_PATH);

        // Remove API prefix
        $uri = preg_replace('#^' . API_PREFIX . '#', '', $uri);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Convert route pattern to regex
            $pattern = preg_replace('#:([a-zA-Z0-9_]+)#', '([a-zA-Z0-9_-]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match

                // Load controller
                $controllerFile = __DIR__ . '/controllers/' . $route['controller'] . '.php';
                if (!file_exists($controllerFile)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Controller not found']);
                    return;
                }

                require_once $controllerFile;

                $controllerClass = $route['controller'];
                $controller = new $controllerClass();
                $action = $route['action'];

                // Call controller action with parameters
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        // No route matched
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}

// Initialize router
$router = new Router();

// Authentication routes
$router->addRoute('POST', '/auth/login', 'AuthController', 'login');
$router->addRoute('GET', '/auth/me', 'AuthController', 'me');
$router->addRoute('POST', '/auth/logout', 'AuthController', 'logout');

// User routes
$router->addRoute('GET', '/users', 'UserController', 'index');
$router->addRoute('POST', '/users', 'UserController', 'create');
$router->addRoute('PUT', '/users/:id', 'UserController', 'update');
$router->addRoute('DELETE', '/users/:id', 'UserController', 'delete');

// Department routes
$router->addRoute('GET', '/departments', 'DepartmentController', 'index');
$router->addRoute('POST', '/departments', 'DepartmentController', 'create');
$router->addRoute('PUT', '/departments/:id', 'DepartmentController', 'update');
$router->addRoute('POST', '/departments/:id/permissions', 'DepartmentController', 'addPermission');
$router->addRoute('GET', '/departments/:id/permissions', 'DepartmentController', 'getPermissions');

// Task routes
$router->addRoute('GET', '/tasks', 'TaskController', 'index');
$router->addRoute('POST', '/tasks', 'TaskController', 'create');
$router->addRoute('GET', '/tasks/:id', 'TaskController', 'show');
$router->addRoute('PUT', '/tasks/:id', 'TaskController', 'update');
$router->addRoute('DELETE', '/tasks/:id', 'TaskController', 'delete');
$router->addRoute('POST', '/tasks/:id/attachments', 'TaskController', 'uploadAttachment');
$router->addRoute('DELETE', '/tasks/:id/attachments/:attachmentId', 'TaskController', 'deleteAttachment');
$router->addRoute('GET', '/tasks/attachments/:id/download', 'TaskController', 'downloadAttachment');

// Config routes
$router->addRoute('GET', '/config/priorities', 'ConfigController', 'getPriorities');
$router->addRoute('GET', '/config/statuses', 'ConfigController', 'getStatuses');
$router->addRoute('GET', '/config/main-topics', 'ConfigController', 'getMainTopics');
$router->addRoute('GET', '/config/sub-topics', 'ConfigController', 'getSubTopics');
$router->addRoute('POST', '/config/priorities', 'ConfigController', 'createPriority');
$router->addRoute('POST', '/config/statuses', 'ConfigController', 'createStatus');
$router->addRoute('POST', '/config/main-topics', 'ConfigController', 'createMainTopic');
$router->addRoute('POST', '/config/sub-topics', 'ConfigController', 'createSubTopic');

return $router;
