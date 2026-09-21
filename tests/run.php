<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Meocox\Air;
use Meocox\Auth;
use Meocox\Config;
use Meocox\Config\Dotenv;
use Meocox\Container\Container;
use Meocox\Database\Connection;
use Meocox\Database\Hydrator;
use Meocox\Database\Model;
use Meocox\Database\QueryBuilder;
use Meocox\Database\Table;
use Meocox\DB;
use Meocox\Http\Request;
use Meocox\Http\Response;
use Meocox\Router\LayoutResolver;
use Meocox\Router\RouteMatcher;
use Meocox\Router\Router;
use Meocox\Router\ViewRenderer;
use Meocox\Utils\Arr;
use Meocox\Utils\Func;
use Meocox\Utils\JWT;
use Meocox\Utils\Str;
use Meocox\Validation\ValidationException;
use Meocox\Validation\Validator;

$passed = 0;
$failed = 0;

function it(string $description, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        echo "  \033[32m✔\033[0m {$description}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  \033[31m✖\033[0m {$description}\n";
        echo "    \033[31mError: {$e->getMessage()}\033[0m in {$e->getFile()}:{$e->getLine()}\n";
        $failed++;
    }
}

function assertEq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $eStr = var_export($expected, true);
        $aStr = var_export($actual, true);
        throw new RuntimeException("Assertion failed: expected {$eStr}, got {$aStr}. {$msg}");
    }
}

function assertTrue(bool $condition, string $msg = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: condition is not true. {$msg}");
    }
}

function assertFalse(bool $condition, string $msg = ''): void
{
    if ($condition) {
        throw new RuntimeException("Assertion failed: condition is not false. {$msg}");
    }
}

echo "\n\033[1;36m=== Running Meocox Air Test Suite ===\033[0m\n\n";

// ==========================================
// 1. Support (Str & Arr)
// ==========================================
echo "\033[1;33m[1] Support Utilities (Str & Arr)\033[0m\n";

it('Str::camel & Str::snake & Str::studly work correctly', function () {
    assertEq('helloWorld', Str::camel('hello_world'));
    assertEq('hello_world', Str::snake('helloWorld'));
    assertEq('UserProfile', Str::studly('user_profile'));
    assertEq('users', Str::plural('user'));
    assertEq('categories', Str::plural('category'));
});

it('Arr::get & Arr::set support dot notation', function () {
    $arr = ['db' => ['mysql' => ['host' => '127.0.0.1']]];
    assertEq('127.0.0.1', Arr::get($arr, 'db.mysql.host'));
    assertEq('default', Arr::get($arr, 'db.mysql.port', 'default'));

    Arr::set($arr, 'db.mysql.port', 3306);
    assertEq(3306, Arr::get($arr, 'db.mysql.port'));
});

// ==========================================
// 2. Dotenv & Config
// ==========================================
echo "\n\033[1;33m[2] Dotenv & Config\033[0m\n";

it('Dotenv correctly parses quotes, comments and variable expansion', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), 'env_test_');
    file_put_contents($tempEnv, <<<ENV
APP_NAME="Meocox Air"
APP_ENV=production # inline comment
DEBUG=true
PORT=8080
GREETING="Hello, \${APP_NAME}!"
ENV
    );

    $vars = Dotenv::load($tempEnv);
    @unlink($tempEnv);

    assertEq('Meocox Air', $vars['APP_NAME']);
    assertEq('production', $vars['APP_ENV']);
    assertEq('true', $vars['DEBUG']);
    assertEq('Hello, Meocox Air!', $vars['GREETING']);
});

it('Config facade define and dot-access works', function () {
    Config::reset();
    Config::load([
        'app' => ['name' => 'MeocoxApp', 'debug' => true],
        'routing' => ['layouts' => ['/login' => 'auth.layout.php']],
    ]);

    assertEq('MeocoxApp', Config::get('app.name'));
    assertTrue(Config::get('app.debug'));
    assertEq('auth.layout.php', Config::get('routing.layouts./login'));
});

// ==========================================
// 3. HTTP Request & Response & Pipeline
// ==========================================
echo "\n\033[1;33m[3] HTTP Engine & Pipeline\033[0m\n";

it('Request parses inputs, JSON, and headers with strong types', function () {
    $req = new Request(
        query: ['page' => '2', 'active' => 'true'],
        post: ['name' => 'Alice'],
        server: [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/users?page=2',
            'HTTP_AUTHORIZATION' => 'Bearer secret_token_xyz',
            'CONTENT_TYPE' => 'application/json',
        ],
        rawBody: '{"amount": "99.50", "vip": 1}'
    );

    assertEq('POST', $req->method());
    assertEq('/api/users', $req->path());
    assertEq('secret_token_xyz', $req->bearerToken());
    assertEq(2, $req->int('page'));
    assertTrue($req->bool('active'));
    assertEq(99.5, $req->float('amount'));
    assertTrue($req->bool('vip'));
});

it('Pipeline executes onion middleware stack in correct order', function () {
    $req = new Request();
    $trace = [];

    $res = (new Meocox\Http\Pipeline())
        ->send($req)
        ->through([
            function ($request, $next) use (&$trace) {
                $trace[] = 'm1_in';
                $response = $next($request);
                $trace[] = 'm1_out';
                return $response;
            },
            function ($request, $next) use (&$trace) {
                $trace[] = 'm2_in';
                $response = $next($request);
                $trace[] = 'm2_out';
                return $response;
            },
        ])
        ->then(function ($request) use (&$trace) {
            $trace[] = 'handler';
            return Response::json(['ok' => true]);
        });

    assertEq(['m1_in', 'm2_in', 'handler', 'm2_out', 'm1_out'], $trace);
    assertEq(200, $res->getStatusCode());
    assertEq('{"ok":true}', $res->getContent());
});

it('Air::after queues and runs post-response callbacks on terminate', function () {
    Air::reset();
    $ran = false;

    Air::after(function () use (&$ran) {
        $ran = true;
    });

    assertTrue(!$ran);
    Air::terminate();
    assertTrue($ran);
});

// ==========================================
// 4. File-Based Router & 3-Tier Layouts
// ==========================================
echo "\n\033[1;33m[4] Router & 3-Tier Layout System\033[0m\n";

it('RouteMatcher correctly extracts params and handles catch-all', function () {
    $routesDir = sys_get_temp_dir() . '/meocox_routes_' . uniqid();
    mkdir($routesDir . '/users/[id]', 0777, true);
    file_put_contents($routesDir . '/users/[id]/page.php', 'User Page');

    mkdir($routesDir . '/docs/[...slug]', 0777, true);
    file_put_contents($routesDir . '/docs/[...slug]/page.php', 'Docs Page');

    $matchUser = RouteMatcher::match($routesDir, '/users/1024');
    assertTrue($matchUser !== null);
    assertEq('1024', $matchUser['params']['id']);
    assertEq('page', $matchUser['type']);

    $matchDocs = RouteMatcher::match($routesDir, '/docs/guide/routing/layouts');
    assertTrue($matchDocs !== null);
    assertEq('guide/routing/layouts', $matchDocs['params']['slug']);

    // Cleanup
    unlink($routesDir . '/users/[id]/page.php');
    rmdir($routesDir . '/users/[id]');
    rmdir($routesDir . '/users');
    unlink($routesDir . '/docs/[...slug]/page.php');
    rmdir($routesDir . '/docs/[...slug]');
    rmdir($routesDir . '/docs');
    rmdir($routesDir);
});

it('LayoutResolver resolves Tier 2 sibling named layout over default layout', function () {
    $tempDir = sys_get_temp_dir() . '/meocox_layout_' . uniqid();
    mkdir($tempDir . '/auth/login', 0777, true);

    file_put_contents($tempDir . '/layout.php', 'Root Layout');
    file_put_contents($tempDir . '/auth/layout.php', 'Auth Default Layout');
    file_put_contents($tempDir . '/auth/login.layout.php', 'Auth Login Named Layout');
    file_put_contents($tempDir . '/auth/login/page.php', 'Login Page Content');

    $dirChain = [$tempDir, $tempDir . '/auth', $tempDir . '/auth/login'];
    $layouts = LayoutResolver::resolve('/auth/login', $dirChain);

    // Outermost layout should be root layout.php
    assertEq($tempDir . '/layout.php', $layouts[0]);
    // Innermost layout should be login.layout.php (overriding auth/layout.php)
    assertEq($tempDir . '/auth/login.layout.php', $layouts[1]);

    // Cleanup
    unlink($tempDir . '/layout.php');
    unlink($tempDir . '/auth/layout.php');
    unlink($tempDir . '/auth/login.layout.php');
    unlink($tempDir . '/auth/login/page.php');
    rmdir($tempDir . '/auth/login');
    rmdir($tempDir . '/auth');
    rmdir($tempDir);
});

it('ViewRenderer properly stacks slots and respects standalone()', function () {
    $tempPage = tempnam(sys_get_temp_dir(), 'page_');
    $tempLayout = tempnam(sys_get_temp_dir(), 'layout_');

    file_put_contents($tempPage, '<h1>Content: <?= $this->escape($name) ?></h1>');
    file_put_contents($tempLayout, '<main><?= $this->slot() ?></main>');

    $renderer = new ViewRenderer(['name' => '<World>']);
    $pageHtml = $renderer->render($tempPage);

    $layoutRenderer = new ViewRenderer([]);
    $layoutRenderer->setSlotContent($pageHtml);
    $finalHtml = $layoutRenderer->render($tempLayout);

    assertEq('<main><h1>Content: &lt;World&gt;</h1></main>', $finalHtml);

    @unlink($tempPage);
    @unlink($tempLayout);
});

// ==========================================
// 5. Database (RQB, Hydrator, SQLite In-Memory)
// ==========================================
echo "\n\033[1;33m[5] Database Engine (SQLite & RQB)\033[0m\n";

it('Hydrator casts JSON, Booleans, Decimal, and WP serialize correctly', function () {
    $casts = [
        'is_admin' => 'bool',
        'meta' => 'json',
        'price' => 'decimal',
        'wp_opts' => 'wp_serialize',
    ];

    // Inbound
    $inbound = Hydrator::prepareForStorage([
        'is_admin' => true,
        'meta' => ['role' => 'editor'],
        'price' => '19.9900',
        'wp_opts' => ['theme' => 'dark'],
        'hacker_field' => 'drop table',
    ], $casts, ['is_admin', 'meta', 'price', 'wp_opts']);

    assertEq(1, $inbound['is_admin']);
    assertEq('{"role":"editor"}', $inbound['meta']);
    assertEq('19.9900', $inbound['price']);
    assertTrue(str_contains($inbound['wp_opts'], 'theme'));
    assertTrue(!isset($inbound['hacker_field']), 'Whitelist must filter hacker_field');

    // Outbound
    $outbound = Hydrator::castOutbound([
        'is_admin' => '1',
        'meta' => '{"role":"editor"}',
        'price' => '19.9900',
        'wp_opts' => $inbound['wp_opts'],
    ], $casts);

    assertTrue($outbound['is_admin'] === true);
    assertEq(['role' => 'editor'], $outbound['meta']);
    assertEq('19.9900', $outbound['price']);
    assertEq(['theme' => 'dark'], $outbound['wp_opts']);
});

it('QueryBuilder & SQLite Connection support CRUD, Drizzle RQB relations, and transactions', function () {
    $tempDb = tempnam(sys_get_temp_dir(), 'meocox_db_');
    Config::set('database.connections.default', ['driver' => 'sqlite', 'database' => $tempDb]);
    DB::reset();

    $conn = DB::connection();

    // Create tables
    $conn->execute("CREATE TABLE `users` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `name` TEXT NOT NULL,
        `status` TEXT NOT NULL
    )");

    $conn->execute("CREATE TABLE `rewards` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `user_id` INTEGER NOT NULL,
        `points` INTEGER NOT NULL
    )");

    // Insert Users
    $u1 = $conn->table('users')->insert(['name' => 'Alice', 'status' => 'active']);
    $u2 = $conn->table('users')->insert(['name' => 'Bob', 'status' => 'active']);

    // Insert Rewards
    $conn->table('rewards')->insert(['user_id' => $u1, 'points' => 100]);
    $conn->table('rewards')->insert(['user_id' => $u1, 'points' => 200]);
    $conn->table('rewards')->insert(['user_id' => $u2, 'points' => 50]);

    // Test Model class with RQB relations
    class TestRewardModel extends Model {
        protected static ?string $table = 'rewards';
    }

    class TestUserModel extends Model {
        protected static ?string $table = 'users';
        protected static array $relations = [
            'rewards' => [
                'type' => 'hasMany',
                'model' => TestRewardModel::class,
                'foreignKey' => 'user_id',
                'localKey' => 'id',
            ],
        ];
    }

    // Query with RQB `with`
    $qb = new QueryBuilder($conn, 'users');
    $qb->setModelClass(TestUserModel::class);
    $qb->with(['rewards']);
    $results = $qb->get();

    assertEq(2, count($results));
    assertEq('Alice', $results[0]['name']);
    assertEq(2, count($results[0]['rewards']));
    assertEq(100, (int) $results[0]['rewards'][0]['points']);
    assertEq(1, count($results[1]['rewards']));

    // Test Nested Transactions with Savepoint
    $conn->transaction(function ($c) {
        $c->table('users')->insert(['name' => 'Charlie', 'status' => 'active']);

        // Nested
        $c->transaction(function ($c2) {
            $c2->table('users')->insert(['name' => 'David', 'status' => 'active']);
        });
    });

    $count = $conn->table('users')->count();
    assertEq(4, $count);

    @unlink($tempDb);
});

// ==========================================
// 6. Validation & Container
// ==========================================
echo "\n\033[1;33m[6] Validation & Container DI\033[0m\n";

it('Validator verifies required, email, min, max, in rules', function () {
    $validator = Validator::make([
        'email' => 'alice@example.com',
        'age' => 25,
        'role' => 'admin',
    ], [
        'email' => 'required|email',
        'age' => 'required|int|min:18',
        'role' => 'required|in:admin,user',
    ]);

    assertTrue($validator->passes());
    $validated = $validator->validated();
    assertEq('alice@example.com', $validated['email']);

    // Failing case
    $badValidator = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
    assertTrue($badValidator->fails());
});

it('Container automatically resolves class dependencies via reflection', function () {
    class ServiceA {
        public function getHello(): string { return 'Hello from A'; }
    }

    class ServiceB {
        public function __construct(public ServiceA $serviceA) {}
    }

    $c = new Container();
    $b = $c->make(ServiceB::class);

    assertTrue($b instanceof ServiceB);
    assertEq('Hello from A', $b->serviceA->getHello());
});

it('Auth facade handles decoupled resolver and session context', function () {
    Auth::reset();
    Auth::resolver(function ($req) {
        return ['id' => 999, 'name' => 'Simon'];
    });

    assertTrue(Auth::check());
    assertEq(999, Auth::id());
    assertEq('Simon', Auth::user()['name']);

    Auth::logout();
    assertTrue(Auth::guest());
});

it('Air::registerAliases supports default aliases and custom multi-aliases', function () {
    Config::set('aliases', [
        'MeocoxAir' => \Meocox\Air::class,
        'AppDB'     => \Meocox\DB::class,
    ]);

    Air::registerAliases();

    // 默认别名有效性
    assertTrue(class_exists('Air', false));
    assertTrue(class_exists('Auth', false));
    assertTrue(class_exists('DB', false));

    // 自定义多别名有效性
    assertTrue(class_exists('MeocoxAir', false));
    assertTrue(class_exists('AppDB', false));

    assertEq(\Meocox\Air::VERSION, 'MeocoxAir'::version());
    assertEq('Meocox\Air', (new \ReflectionClass('MeocoxAir'))->getName());

    $helperContent = Air::generateIdeHelper();
    assertTrue(str_contains($helperContent, 'class Air extends \Meocox\Air'));
    assertTrue(str_contains($helperContent, 'class MeocoxAir extends \Meocox\Air'));
    assertTrue(str_contains($helperContent, 'class DB extends \Meocox\DB'));
});

it('Air full lifecycle executes loader pre-flight, renders pages with layouts, and runs after callbacks', function () {
    $tempRoutes = sys_get_temp_dir() . '/meocox_e2e_' . uniqid();
    mkdir($tempRoutes . '/admin', 0777, true);

    // loader.php in admin: rejects if missing X-Admin-Token
    file_put_contents($tempRoutes . '/admin/loader.php', '<?php return function ($req) {
        if ($req->header("X-Admin-Token") !== "supersecret") {
            return \Meocox\Http\Response::json(["error" => "Unauthorized"], 401);
        }
        return ["loadedUser" => "SuperAdmin"];
    };');

    // layout.php
    file_put_contents($tempRoutes . '/layout.php', '<div class="root"><?php \Meocox\View::slot(); ?></div>');

    // admin/page.php
    file_put_contents($tempRoutes . '/admin/page.php', '<h1>Admin Dashboard - <?= $loadedUser ?></h1>');

    $router = new Router($tempRoutes);

    // 1. Test unauthorized request (loader short-circuits)
    $req1 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin']);
    $res1 = $router->dispatch($req1);
    assertEq(401, $res1->getStatusCode());
    assertEq('{"error":"Unauthorized"}', $res1->getContent());

    // 2. Test authorized request with layout wrapping and loader data injection
    $req2 = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/admin',
        'HTTP_X_ADMIN_TOKEN' => 'supersecret',
    ]);
    $res2 = $router->dispatch($req2);
    assertEq(200, $res2->getStatusCode());
    assertEq('<div class="root"><h1>Admin Dashboard - SuperAdmin</h1></div>', $res2->getContent());

    // Cleanup
    unlink($tempRoutes . '/admin/loader.php');
    unlink($tempRoutes . '/admin/page.php');
    unlink($tempRoutes . '/layout.php');
    rmdir($tempRoutes . '/admin');
    rmdir($tempRoutes);
});

it('Router handles loading.php full page deferral and X-MeocoxAir-Target async resolution', function () {
    $tempRoutes = sys_get_temp_dir() . '/meocox_suspense_' . uniqid();
    mkdir($tempRoutes . '/dashboard', 0777, true);

    file_put_contents($tempRoutes . '/layout.php', '<nav>Header</nav><main><?= $this->slot() ?></main>');
    file_put_contents($tempRoutes . '/dashboard/loading.php', '<div class="skeleton">Loading...</div>');
    file_put_contents($tempRoutes . '/dashboard/page.php', '<h1>Real Dashboard Content</h1>');

    $router = new Router($tempRoutes);

    // 1. First full request: should return layout + loading.php skeleton with data-air-suspense
    $req1 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
    $res1 = $router->dispatch($req1);
    assertEq(200, $res1->getStatusCode());
    assertTrue(str_contains($res1->getContent(), '<nav>Header</nav>'));
    assertTrue(str_contains($res1->getContent(), 'data-air-suspense="/dashboard"'));
    assertTrue(str_contains($res1->getContent(), 'data-air-target="page"'));
    assertTrue(str_contains($res1->getContent(), '<div class="skeleton">Loading...</div>'));

    // 2. Secondary async request with X-MeocoxAir-Target: page
    $req2 = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/dashboard',
        'HTTP_X_MEOCOXAIR_TARGET' => 'page',
    ]);
    $res2 = $router->dispatch($req2);
    assertEq(200, $res2->getStatusCode());
    // Should NOT contain outer layout, should ONLY be real page content!
    assertFalse(str_contains($res2->getContent(), '<nav>Header</nav>'));
    assertEq('<h1>Real Dashboard Content</h1>', $res2->getContent());

    // Cleanup
    unlink($tempRoutes . '/dashboard/loading.php');
    unlink($tempRoutes . '/dashboard/page.php');
    unlink($tempRoutes . '/layout.php');
    rmdir($tempRoutes . '/dashboard');
    rmdir($tempRoutes);
});

it('Router handles error boundary wrapped inside parent layouts', function () {
    $tempRoutes = sys_get_temp_dir() . '/meocox_error_' . uniqid();
    mkdir($tempRoutes . '/dashboard', 0777, true);

    file_put_contents($tempRoutes . '/layout.php', '<header>Navbar</header><main><?= $this->slot() ?></main>');
    file_put_contents($tempRoutes . '/dashboard/error.php', '<div class="error-panel">Error: <?= $this->error->getMessage() ?></div>');
    file_put_contents($tempRoutes . '/dashboard/page.php', '<?php throw new \RuntimeException("Database Connection Failed"); ?>');

    $router = new Router($tempRoutes);
    $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
    $res = $router->dispatch($req);

    assertEq(500, $res->getStatusCode());
    // Layout must be intact!
    assertTrue(str_contains($res->getContent(), '<header>Navbar</header>'));
    assertTrue(str_contains($res->getContent(), 'Error: Database Connection Failed'));

    // Cleanup
    unlink($tempRoutes . '/dashboard/error.php');
    unlink($tempRoutes . '/dashboard/page.php');
    unlink($tempRoutes . '/layout.php');
    rmdir($tempRoutes . '/dashboard');
    rmdir($tempRoutes);
});

it('ViewRenderer suspense helper and Air::injectSuspenseRuntime work', function () {
    $renderer = new ViewRenderer([], new Request());
    $html = $renderer->suspense(
        component: 'StatsWidget',
        fallback: '<div class="shimmer">Skeleton</div>',
        props: ['range' => '7d']
    );

    assertTrue(str_contains($html, 'data-air-suspense="?_air_component=StatsWidget&amp;range=7d"'));
    assertTrue(str_contains($html, 'data-air-component="StatsWidget"'));
    assertTrue(str_contains($html, '<div class="shimmer">Skeleton</div>'));

    // Test Air::injectSuspenseRuntime
    $pageHtml = '<html><body>' . $html . '</body></html>';
    $response = \Meocox\Http\Response::html($pageHtml);
    $injectedRes = \Meocox\Air::injectSuspenseRuntime($response);

    assertTrue(str_contains($injectedRes->getContent(), 'id="meocoxair-runtime"'));
    assertTrue(str_contains($injectedRes->getContent(), 'X-MeocoxAir-Target'));
    assertTrue(str_contains($injectedRes->getContent(), 'X-MeocoxAir-Suspense'));
});

it('View::component direct output and View::componentHTML string return work seamlessly', function () {
    $tempDir = sys_get_temp_dir() . '/meocox_comp_' . uniqid();
    mkdir($tempDir . '/ui', 0777, true);

    // Component file: extracts $name directly, and can also access $props['title']
    $compCode = '<div class="badge badge-<?= $variant ?>"><?= $name ?> - <?= $props[\'title\'] ?></div>';
    file_put_contents($tempDir . '/ui/badge.php', $compCode);

    Config::set('paths.components', $tempDir);

    // 1. Test View::component() direct output
    ob_start();
    \Meocox\View::component('ui.badge', [
        'name' => 'Alice',
        'title' => 'Software Engineer',
        'variant' => 'primary',
    ]);
    $directOutput = ob_get_clean();

    assertEq('<div class="badge badge-primary">Alice - Software Engineer</div>', $directOutput);

    // 2. Test View::componentHTML() return string
    $html = \Meocox\View::componentHTML('ui.badge', [
        'name' => 'Bob',
        'title' => 'Architect',
        'variant' => 'success',
    ]);
    assertEq('<div class="badge badge-success">Bob - Architect</div>', $html);

    // 3. Test global helper functions component() and componentHTML()
    ob_start();
    component('ui.badge', ['name' => 'Charlie', 'title' => 'Designer', 'variant' => 'info']);
    $helperDirect = ob_get_clean();
    assertEq('<div class="badge badge-info">Charlie - Designer</div>', $helperDirect);

    $helperHtml = componentHTML('ui.badge', ['name' => 'David', 'title' => 'PM', 'variant' => 'warning']);
    assertEq('<div class="badge badge-warning">David - PM</div>', $helperHtml);

    // 4. Test View::exists()
    assertTrue(\Meocox\View::exists('ui.badge'));
    assertTrue(!\Meocox\View::exists('non.existent.component'));

    // Cleanup
    unlink($tempDir . '/ui/badge.php');
    rmdir($tempDir . '/ui');
    rmdir($tempDir);
    Config::set('paths.components', null);
});

it('View::slot and View::suspense facades work during template rendering', function () {
    $tempDir = sys_get_temp_dir() . '/meocox_view_facade_' . uniqid();
    mkdir($tempDir, 0777, true);

    // Layout using View::slot()
    file_put_contents($tempDir . '/layout.php', '<nav>Menu</nav><section><?= \Meocox\View::slot() ?></section>');

    $renderer = new ViewRenderer([], new Request());
    $renderer->setSlotContent('<h1>Content from Page</h1>');

    $output = $renderer->render($tempDir . '/layout.php');
    assertEq('<nav>Menu</nav><section><h1>Content from Page</h1></section>', $output);

    // Test static suspense via View facade
    $suspenseHtml = \Meocox\View::suspense('RecentOrders', '<div>Loading...</div>', ['limit' => 5]);
    assertTrue(str_contains($suspenseHtml, 'data-air-component="RecentOrders"'));
    assertTrue(str_contains($suspenseHtml, 'limit=5'));

    // Cleanup
    unlink($tempDir . '/layout.php');
    rmdir($tempDir);
});

it('Air::handleProxyFile intercepts request globally (Next.js 16 proxy.ts convention)', function () {
    $tempDir = sys_get_temp_dir() . '/meocox_proxy_' . uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir . '/proxy.php', '<?php return function ($req) {
        if ($req->path() === "/maintenance") {
            return \Meocox\Http\Response::html("Maintenance Mode", 503);
        }
        return null;
    };');

    $req1 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/maintenance']);
    $res1 = \Meocox\Air::handleProxyFile($req1, $tempDir);
    assertTrue($res1 instanceof \Meocox\Http\Response);
    assertEq(503, $res1->getStatusCode());
    assertEq('Maintenance Mode', $res1->getContent());

    $req2 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/normal']);
    $res2 = \Meocox\Air::handleProxyFile($req2, $tempDir);
    assertTrue($res2 === null);

    // Cleanup
    unlink($tempDir . '/proxy.php');
    rmdir($tempDir);
});

it('Router enforces route.php and page.php mutual exclusion (Next.js rule)', function () {
    $tempDir = sys_get_temp_dir() . '/meocox_conflict_' . uniqid();
    mkdir($tempDir . '/test', 0777, true);

    file_put_contents($tempDir . '/test/route.php', '<?php return fn() => "api";');
    file_put_contents($tempDir . '/test/page.php', '<h1>Page</h1>');

    $router = new Router($tempDir);
    $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);

    $caught = false;
    try {
        $router->dispatch($req);
    } catch (\LogicException $e) {
        $caught = true;
        assertTrue(str_contains($e->getMessage(), 'Conflict detected'));
    }

    assertTrue($caught);

    // Cleanup
    unlink($tempDir . '/test/route.php');
    unlink($tempDir . '/test/page.php');
    rmdir($tempDir . '/test');
    rmdir($tempDir);
});

it('Top-Down layout pipeline executes outer-to-inner with View::slot() zero-buffer require', function () {
    $tempDir = sys_get_temp_dir() . '/meocox_pipeline_' . uniqid();
    mkdir($tempDir . '/admin/users', 0777, true);

    // Root layout
    file_put_contents($tempDir . '/layout.php', '<div class="root"><?php \Meocox\View::slot(); ?></div>');
    // Admin layout
    file_put_contents($tempDir . '/admin/layout.php', '<div class="admin"><nav>AdminNav</nav><?php \Meocox\View::slot(); ?></div>');
    // Loader
    file_put_contents($tempDir . '/admin/users/loader.php', '<?php return ["title" => "User Management"];');
    // Page
    file_put_contents($tempDir . '/admin/users/page.php', '<main><h1><?= $title ?></h1></main>');

    $router = new Router($tempDir);
    $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/users']);
    $res = $router->dispatch($req);

    assertEq(200, $res->getStatusCode());
    $expected = '<div class="root"><div class="admin"><nav>AdminNav</nav><main><h1>User Management</h1></main></div></div>';
    assertEq($expected, $res->getContent());

    // Cleanup
    unlink($tempDir . '/admin/users/page.php');
    unlink($tempDir . '/admin/users/loader.php');
    unlink($tempDir . '/admin/layout.php');
    unlink($tempDir . '/layout.php');
    rmdir($tempDir . '/admin/users');
    rmdir($tempDir . '/admin');
    rmdir($tempDir);
});

// ==========================================
// 7. Advanced Utilities, Container & HTTP Improvements
// ==========================================
echo "\n\033[1;33m[7] Advanced Utilities, Container & HTTP Improvements\033[0m\n";

it('JWT encodes, decodes and verifies signatures with expiration control', function () {
    $secret = 'super-secret-key-12345';
    $payload = [
        'sub' => 10086,
        'role' => 'admin',
        'exp' => time() + 3600,
    ];

    $token = JWT::encode($payload, $secret);
    assertTrue(is_string($token) && substr_count($token, '.') === 2);

    $decoded = JWT::decode($token, $secret);
    assertTrue(is_array($decoded));
    assertEq(10086, $decoded['sub']);
    assertEq('admin', $decoded['role']);

    // Invalid secret
    assertEq(null, JWT::decode($token, 'wrong-secret'));

    // Expired token
    $expiredToken = JWT::encode(['sub' => 1, 'exp' => time() - 10], $secret);
    assertEq(null, JWT::decode($expiredToken, $secret));

    // Tampered payload
    $parts = explode('.', $token);
    $parts[1] = JWT::base64UrlEncode((string) json_encode(['sub' => 99999, 'role' => 'root']));
    $tamperedToken = implode('.', $parts);
    assertEq(null, JWT::decode($tamperedToken, $secret));
});

it('Func::retry retries failures with backoff and invokes fallbacks', function () {
    $attempts = 0;
    $res = Func::retry(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException("Attempt {$attempts} failed");
        }
        return 'success';
    }, ['retries' => 3, 'delay' => 1]);

    assertEq('success', $res);
    assertEq(3, $attempts);

    // Reaching max retries with fallback
    $failedAttempts = 0;
    $fallbackRes = Func::retry(function () use (&$failedAttempts) {
        $failedAttempts++;
        throw new RuntimeException("Fail forever");
    }, [
        'retries' => 2,
        'delay' => 1,
        'onFinalError' => fn($e) => 'recovered',
    ]);
    assertEq('recovered', $fallbackRes);
    assertEq(2, $failedAttempts);
});

it('Container supports has, alias, forget, flush and self/parent type resolution', function () {
    $container = new Container();

    class ServiceAlpha {
        public string $name = 'Alpha';
    }

    class ServiceBetaParent {
        public string $role = 'Parent';
    }

    class ServiceBeta extends ServiceBetaParent {
        public function __construct(public ?parent $parentInstance = null) {}
    }

    $container->singleton(ServiceAlpha::class);
    $container->alias(ServiceAlpha::class, 'alpha_alias');

    assertTrue($container->has(ServiceAlpha::class));
    assertTrue($container->has('alpha_alias'));
    assertFalse($container->has('non_existent_service'));

    $alpha1 = $container->make('alpha_alias');
    $alpha2 = $container->make(ServiceAlpha::class);
    assertTrue($alpha1 === $alpha2);

    $container->forget('alpha_alias');
    $alpha3 = $container->make(ServiceAlpha::class);
    assertTrue($alpha1 !== $alpha3);

    // parent resolution test
    $beta = $container->make(ServiceBeta::class);
    assertTrue($beta->parentInstance instanceof ServiceBetaParent);

    $container->flush();
    assertFalse($container->has('alpha_alias'));
});

it('Response handles multiple Set-Cookie headers and withCookie helper', function () {
    $response = new Response('ok', 200);
    $response->withCookie('session_id', 'xyz123', ['path' => '/', 'httpOnly' => true])
             ->withCookie('theme', 'dark', ['maxAge' => 86400]);

    $headers = $response->getHeaders();
    assertTrue(isset($headers['Set-Cookie']));
    assertTrue(is_array($headers['Set-Cookie']));
    assertEq(2, count($headers['Set-Cookie']));
    assertTrue(str_contains($headers['Set-Cookie'][0], 'session_id=xyz123'));
    assertTrue(str_contains($headers['Set-Cookie'][0], 'HttpOnly'));
    assertTrue(str_contains($headers['Set-Cookie'][1], 'theme=dark'));
    assertTrue(str_contains($headers['Set-Cookie'][1], 'Max-Age=86400'));
});

it('Router cascades not-found.php upwards and wraps in parent layouts', function () {
    $tempDir = sys_get_temp_dir() . '/meocox_notfound_' . uniqid();
    mkdir($tempDir . '/shop/cart', 0777, true);

    // Root layout
    file_put_contents($tempDir . '/layout.php', '<div class="root"><?= \Meocox\View::slot() ?></div>');
    // Shop layout
    file_put_contents($tempDir . '/shop/layout.php', '<div class="shop"><?= \Meocox\View::slot() ?></div>');
    // Shop not-found
    file_put_contents($tempDir . '/shop/not-found.php', '<p>Shop item not found</p>');

    $router = new Router($tempDir);
    $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/shop/unknown-product']);
    $res = $router->dispatch($req);

    assertEq(404, $res->getStatusCode());
    $expected = '<div class="root"><div class="shop"><p>Shop item not found</p></div></div>';
    assertEq($expected, $res->getContent());

    // Cleanup
    unlink($tempDir . '/shop/not-found.php');
    unlink($tempDir . '/shop/layout.php');
    unlink($tempDir . '/layout.php');
    rmdir($tempDir . '/shop/cart');
    rmdir($tempDir . '/shop');
    rmdir($tempDir);
});

it('QueryBuilder supports between, startsWith, contains, and omit', function () {
    $conn = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $conn->execute('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category TEXT, price REAL, secret TEXT);');
    $conn->execute("INSERT INTO products VALUES (1, 'Apple iPhone', 'phone', 999.0, 's1'), (2, 'Apple Mac', 'laptop', 1999.0, 's2'), (3, 'Samsung Galaxy', 'phone', 799.0, 's3');");

    $builder = new QueryBuilder($conn, 'products');

    // between test
    $betweenSql = $builder->whereBetween('price', [700, 1000])->toSql();
    assertTrue(str_contains($betweenSql, '`price` BETWEEN ? AND ?'));

    // RQB operators & omit
    $qb = new QueryBuilder($conn, 'products');
    $results = $qb->applyParams([
        'where' => [
            'name' => ['startsWith' => 'Apple'],
            'price' => ['between' => [900, 1200]],
        ],
        'omit' => ['secret'],
    ])->get();

    assertEq(1, count($results));
    assertEq('Apple iPhone', $results[0]['name']);
    assertFalse(array_key_exists('secret', $results[0]));
});

it('Air supports wildcard patterns in routing rewrites and redirects', function () {
    Config::set('routing.rewrites', [
        '/api/v1/*' => '/api/v2/*',
    ]);
    Config::set('routing.redirects', [
        '/old-docs/*' => [
            'destination' => '/new-docs/*',
            'permanent' => true,
        ],
    ]);

    // Test redirect wildcard
    $redirectReq = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/old-docs/guide/install']);
    $res = Air::handle($redirectReq);
    assertEq(301, $res->getStatusCode());
    assertEq('/new-docs/guide/install', $res->getHeader('Location'));

    // Reset config
    Config::set('routing.rewrites', []);
    Config::set('routing.redirects', []);
});

echo "Results: \033[32m{$passed} Passed\033[0m, \033[31m{$failed} Failed\033[0m\n\n";

exit($failed > 0 ? 1 : 0);
