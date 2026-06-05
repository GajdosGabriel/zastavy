<?php

namespace App\Services;


use Illuminate\Support\Facades\Storage;
use Image;

use Illuminate\Http\File;


class Images
{

    protected $model;
    protected $request;

    public function __construct($model, $request)
    {
        $this->model = $model;
        $this->request = $request;
    }

    public function handler()
    {
        if ($this->request->images) $this->uploadImages();
    }

    public function uploadImages()
    {

        if (!$this->request->images) return false;

        foreach ($this->request->images as $image) {
            $file_name = $this->model->slug . '-' . rand(1000, 90000) . '.' .  $image->extension();
            $url = $image->storeAs($this->folderPath(), $file_name, 'public');

            $this->image = $this->model->images()->create([
                'url' => $this->folderPath() . basename($url),
                'name' => $this->model->slug,
                'thumb' => $this->folderPath() . 'thumb/' . basename($url),
                'org_name' => $image->getClientOriginalName(),
                'size' => $image->getClientOriginalExtension(),
                'mime' => $image->extension()
            ]);



            $this->resizePostImage($big = 1000, $thumb = 280);
        }
    }

    protected function folderPath()
    {
        return strtolower(class_basename($this->model)) . 's/' . $this->model->user_id . '/';
    }

    protected function resizePostImage($big, $thumb)
    {

        $image = array('jpg', 'jpeg', 'gif', 'png');
        if (!in_array(strtolower($this->image->mime), $image)) return;


        $this->image->update(['type' => 'img']);

        $img = Image::make(Storage::disk('public')->get($this->image->url));
        $img->widen($big)->save(storage_path('app/public/' . $this->image->url));


        Storage::disk('public')->makeDirectory($this->folderPath() . '/thumb');
        $img->widen($thumb)->save(storage_path('app/public/' . $this->folderPath() . '/thumb/' . basename($this->image->url)));
    }

    protected function createDirectory()
    {
        Storage::disk('public')->makeDirectory($this->folderPath());
        Storage::disk('public')->makeDirectory($this->folderPath() . '/thumb');
    }


}
