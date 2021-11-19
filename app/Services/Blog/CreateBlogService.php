<?php

namespace App\Services\Blog;

use App\Models\Blog;

class CreateBlogService{


    public function CreateBlog($param)
    {
        if(Blog::where('url',$param->url)->first()!=null)
            return 422;

        $primary=false;
        if(auth()->user()->Blogs->isEmpty())
            $primary=true;

        
        $blog=Blog::create([
            'title'=>$param->title,
            'url'=>$param->url,
            'privacy'=>$param->privacy,
            'password'=>$param->password,
        ]);

        $blog->Users()->create([
            'user_id'=>auth()->id(),
            'primary'=>$primary,
            'full_privileges'=>'true',
            'contributor_privileges'=>'false'
        ]);

        return 201;
    }

}
