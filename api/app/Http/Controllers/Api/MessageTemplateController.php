<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function index()
    {
        return response()->json(['data' => MessageTemplate::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        return response()->json(['data' => MessageTemplate::create($this->validated($request))], 201);
    }

    public function update(Request $request, MessageTemplate $messageTemplate)
    {
        $messageTemplate->update($this->validated($request));

        return response()->json(['data' => $messageTemplate]);
    }

    public function destroy(MessageTemplate $messageTemplate)
    {
        $messageTemplate->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        // Dĺžka tela zodpovedá limitu správy zákazníkovi.
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'body' => 'required|string|max:5000',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data['sort_order'] ??= 99;

        return $data;
    }
}
