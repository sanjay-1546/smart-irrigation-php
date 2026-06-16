<?php
declare(strict_types=1);

/**
 * Custom 404 handler, wired via ErrorDocument in .htaccess. Returns JSON for
 * API clients (NodeMCU, dashboard, mobile app) and a plain HTML page for
 * anyone hitting the backend directly in a browser.
 */

http_response_code(404);

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsJson = str_starts_with($requestUri, '/api/')
    || str_contains($requestUri, '/api/')
    || str_contains($accept, 'application/json');

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'The requested endpoint was not found',
        'errors' => [],
    ]);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 - Not Found | Smart Farm Irrigation Backend</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0f1a14;
      color: #e6f4ea;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .card {
      text-align: center;
      padding: 2.5rem 3rem;
      border: 1px solid #244a33;
      border-radius: 12px;
      background: #142a1d;
      max-width: 480px;
    }
    h1 { font-size: 3rem; margin: 0 0 0.5rem; color: #5fd97e; }
    p { margin: 0.5rem 0; color: #b9d6c2; }
    a {
      display: inline-block;
      margin-top: 1.5rem;
      color: #0f1a14;
      background: #5fd97e;
      padding: 0.6rem 1.2rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
    code {
      background: #0f1a14;
      padding: 0.1rem 0.4rem;
      border-radius: 4px;
      color: #5fd97e;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>404</h1>
    <p>This route doesn't exist on the Smart Farm Irrigation Backend.</p>
    <p>Looking for the API? Check <code>/api/docs/index.php</code>.</p>
    <a href="/api/docs/index.php">View API Docs</a>
  </div>
</body>
</html>
