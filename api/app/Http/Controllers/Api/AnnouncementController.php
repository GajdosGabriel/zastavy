<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function active(Request $request)
    {
        $announcements = Announcement::query()
            ->activeForPublic()
            ->when($request->filled('placement'), function ($query) use ($request) {
                $query->where('placement', $request->string('placement')->toString());
            })
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return AnnouncementResource::collection($announcements);
    }
}
