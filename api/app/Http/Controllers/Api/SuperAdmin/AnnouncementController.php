<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    public function index()
    {
        Gate::authorize('announcements.manage');

        return AnnouncementResource::collection(
            Announcement::query()->latest()->paginate()
        )->additional($this->formOptions());
    }

    public function store(AnnouncementRequest $request)
    {
        Gate::authorize('announcements.manage');

        $announcement = Announcement::create($this->payload($request));

        return (new AnnouncementResource($announcement))
            ->additional($this->formOptions());
    }

    public function show(Announcement $announcement)
    {
        Gate::authorize('announcements.manage');

        return (new AnnouncementResource($announcement))
            ->additional($this->formOptions());
    }

    public function update(AnnouncementRequest $request, Announcement $announcement)
    {
        Gate::authorize('announcements.manage');

        $announcement->update($this->payload($request));

        return (new AnnouncementResource($announcement->refresh()))
            ->additional($this->formOptions());
    }

    public function destroy(Announcement $announcement)
    {
        Gate::authorize('announcements.manage');

        $announcement->delete();

        return response()->noContent();
    }

    private function payload(AnnouncementRequest $request): array
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'meta' => [
                'statuses' => ModelStatus::allowedForUser(request()->user()),
                'placements' => [
                    ['value' => 'top', 'label' => 'Horný banner'],
                    ['value' => 'bottom', 'label' => 'Dolný oznam'],
                ],
                'style_classes' => [
                    ['value' => 'bg-sky-700 text-gray-100', 'label' => 'Modrá'],
                    ['value' => 'bg-emerald-600 text-gray-100', 'label' => 'Zelená'],
                    ['value' => 'bg-orange-700 text-gray-100', 'label' => 'Oranžová'],
                    ['value' => 'bg-rose-700 text-gray-100', 'label' => 'Červená'],
                    ['value' => 'bg-fuchsia-700 text-gray-100', 'label' => 'Fialová'],
                    ['value' => 'bg-blue-500 text-gray-200', 'label' => 'Svetlá modrá'],
                    ['value' => 'bg-slate-800 text-gray-100', 'label' => 'Tmavá'],
                ],
            ],
        ];
    }
}
