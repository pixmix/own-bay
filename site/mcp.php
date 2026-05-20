<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['jsonrpc']) || $input['jsonrpc'] !== '2.0') {
    echo json_encode(['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid request'], 'id' => null]);
    exit;
}

$method = $input['method'] ?? '';
$params = $input['params'] ?? [];
$id = $input['id'] ?? null;

function mcp_result($id, $result): void {
    echo json_encode(['jsonrpc' => '2.0', 'result' => $result, 'id' => $id]);
    exit;
}

function mcp_error($id, int $code, string $message): void {
    echo json_encode(['jsonrpc' => '2.0', 'error' => ['code' => $code, 'message' => $message], 'id' => $id]);
    exit;
}

switch ($method) {
    case 'initialize':
        mcp_result($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => [
                'name' => SITE_TITLE . ' MCP',
                'version' => '1.0.0',
            ],
        ]);

    case 'tools/list':
        mcp_result($id, ['tools' => [
            [
                'name' => 'list_items',
                'description' => 'List all available items for sale. Returns title, price, tags, and item ID for each.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'tag' => ['type' => 'string', 'description' => 'Filter by tag name (optional)'],
                        'search' => ['type' => 'string', 'description' => 'Search in title and description (optional)'],
                        'limit' => ['type' => 'integer', 'description' => 'Max items to return (default 50)', 'default' => 50],
                    ],
                ],
            ],
            [
                'name' => 'get_item',
                'description' => 'Get full details for a specific item including description, images, tags, offer count, and whether an offer at or above the listed price exists.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'item_id' => ['type' => 'string', 'description' => 'The item ID'],
                    ],
                    'required' => ['item_id'],
                ],
            ],
            [
                'name' => 'list_tags',
                'description' => 'List all tags with item counts, sorted by prevalence.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'submit_offer',
                'description' => 'Submit an offer on an item. Requires the buyer email and offer amount. The email is sent to the seller and not stored on the server.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'item_id' => ['type' => 'string', 'description' => 'The item ID'],
                        'email' => ['type' => 'string', 'description' => 'Buyer email address'],
                        'amount' => ['type' => 'number', 'description' => 'Offer amount in ' . CURRENCY],
                    ],
                    'required' => ['item_id', 'email', 'amount'],
                ],
            ],
            [
                'name' => 'get_site_info',
                'description' => 'Get site metadata: title, tagline, currency, and offer rules.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]]);

    case 'tools/call':
        $tool = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        switch ($tool) {
            case 'list_items':
                $items = get_all_available_items();
                $tag_filter = $args['tag'] ?? '';
                $search = strtolower($args['search'] ?? '');
                $limit = min((int)($args['limit'] ?? 50), 100);

                $results = [];
                foreach ($items as $item) {
                    $tags = get_item_tags($item);
                    if ($tag_filter && !in_array($tag_filter, $tags, true)) continue;
                    if ($search) {
                        $haystack = strtolower($item['title'] . ' ' . ($item['description'] ?? '') . ' ' . implode(' ', $tags));
                        if (strpos($haystack, $search) === false) continue;
                    }
                    $results[] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'price' => (float)$item['price'],
                        'currency' => CURRENCY,
                        'tags' => $tags,
                        'offer_count' => count_offers_for_item($item['id']),
                    ];
                    if (count($results) >= $limit) break;
                }

                mcp_result($id, ['content' => [['type' => 'text', 'text' => json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]]);

            case 'get_item':
                $item_id = $args['item_id'] ?? '';
                $item = get_item($item_id);
                if (!$item || $item['status'] !== 'available') {
                    mcp_result($id, ['content' => [['type' => 'text', 'text' => 'Item not found or not available.']], 'isError' => true]);
                }

                $images = get_item_images($item);
                $tags = get_item_tags($item);
                $offers = get_offers_for_item($item_id);
                $has_above = false;
                foreach ($offers as $o) {
                    if ($o['amount'] >= $item['price']) { $has_above = true; break; }
                }

                $image_urls = array_map(function($img) {
                    return SITE_URL . '/' . $img;
                }, $images);

                $result = [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'price' => (float)$item['price'],
                    'currency' => CURRENCY,
                    'tags' => $tags,
                    'images' => $image_urls,
                    'offer_count' => count($offers),
                    'has_offer_at_or_above_price' => $has_above,
                ];

                mcp_result($id, ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]]);

            case 'list_tags':
                $items = get_all_available_items();
                $tags = get_all_tags($items);
                mcp_result($id, ['content' => [['type' => 'text', 'text' => json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]]);

            case 'submit_offer':
                $item_id = $args['item_id'] ?? '';
                $email = trim($args['email'] ?? '');
                $amount = (float)($args['amount'] ?? 0);

                $item = get_item($item_id);
                if (!$item || $item['status'] !== 'available') {
                    mcp_result($id, ['content' => [['type' => 'text', 'text' => 'Item not found or not available.']], 'isError' => true]);
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    mcp_result($id, ['content' => [['type' => 'text', 'text' => 'Invalid email address.']], 'isError' => true]);
                }
                if ($amount <= 0) {
                    mcp_result($id, ['content' => [['type' => 'text', 'text' => 'Offer amount must be positive.']], 'isError' => true]);
                }

                $db = get_db();
                $db->prepare('INSERT INTO offers (item_id, amount, created_at) VALUES (?, ?, ?)')
                   ->execute([$item_id, $amount, date('Y-m-d\TH:i:s+00:00')]);
                $offer_id = (int)$db->lastInsertId();

                send_offer_notification($item, $amount, $email, $offer_id);
                send_offer_confirmation($item, $amount, $email, $offer_id);

                $above = $amount >= $item['price'] ? 'yes' : 'no';
                mcp_result($id, ['content' => [['type' => 'text', 'text' => json_encode([
                    'offer_id' => $offer_id,
                    'item' => $item['title'],
                    'amount' => $amount,
                    'currency' => CURRENCY,
                    'meets_or_exceeds_price' => $above,
                    'message' => 'Offer submitted. Confirmation sent to ' . $email,
                ], JSON_PRETTY_PRINT)]]]);

            case 'get_site_info':
                mcp_result($id, ['content' => [['type' => 'text', 'text' => json_encode([
                    'title' => SITE_TITLE,
                    'tagline' => SITE_TAGLINE,
                    'currency' => CURRENCY,
                    'url' => SITE_URL,
                    'offer_rules' => [
                        'The first highest offer has priority.',
                        'Once an offer meets or exceeds the listed price, that offer takes absolute priority — even if a higher offer arrives later.',
                        'Items remain visible until a sale is finalised.',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]]);

            default:
                mcp_result($id, ['content' => [['type' => 'text', 'text' => 'Unknown tool: ' . $tool]], 'isError' => true]);
        }
        break;

    case 'notifications/initialized':
        echo json_encode(['jsonrpc' => '2.0', 'result' => null, 'id' => $id]);
        exit;

    default:
        mcp_error($id, -32601, 'Method not found: ' . $method);
}
