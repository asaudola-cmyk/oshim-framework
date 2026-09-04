<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Item;
use Oshim\Http\Request;
use Oshim\Http\Response;

class ItemController
{
    public static function index(): Response
    {
        $items = Item::paginate(15);
        return Response::json($items->toArray());
    }

    public static function store(Request $request): Response
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $item = Item::create($data);
        return Response::json(['success' => true, 'item' => $item]);
    }
}