<?php

namespace App\Http\Resources;

use App\Models\Blog;
use App\Models\Follow;
use App\Models\PostNotes;
use Illuminate\Http\Resources\Json\JsonResource;

class LastNdaysActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $blog=$this[0];

        $postsIds=$blog->Posts()->pluck('id')->toArray();

        $notes=PostNotes::whereIn('post_id',$postsIds)->where('created_at',$this[1]->format('Y-m-d'))->count();
        $newFollowers=Follow::where('blog_id',$blog->id)->where('created_at',$this[1]->format('Y-m-d'))->count();
        $totalFollowers=Follow::where('blog_id',$blog->id)->where('created_at','<',$this[1]->format('Y-m-d'))->count();

        return [
            'notes'=>$notes,
            'new followers'=>$newFollowers,
            'total followers'=>$totalFollowers
        ];
    }
}
