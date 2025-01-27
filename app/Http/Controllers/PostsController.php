<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Blog;
use App\Models\Posts;
use App\Models\Follow;
use App\Models\BlogUser;
use Illuminate\Http\Request;
use App\Http\Misc\Helpers\Config;
use App\Http\Misc\Helpers\Errors;
use App\Http\Requests\PostRequest;
use App\Services\User\UserService;
use App\Services\Posts\PostsService;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\PostsResource;
use App\Http\Resources\BlogCollection;
use App\Http\Resources\PostsCollection;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\DraftPostCollection;
use App\Http\Resources\DraftPostResource;
use App\Http\Resources\PostEditViewResource;
use App\Http\Resources\TaggedPostsCollection;

class PostsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Posts Controller
    |--------------------------------------------------------------------------|
    | This controller handles the processes of Posts:
    | Create, edit and update Posts
    | retrieve posts (dashboard , by blogname , post_id)
    | retrieve posts (draft, by blogname)
    | retrieve posts (explore, randomly)
    |
   */

    protected $PostsService;
    protected $UserService;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct(PostsService $PostsService, UserService $UserService)
    {
        $this->PostsService = $PostsService;
        $this->UserService = $UserService;
    }


    /**
     * @OA\Post(
     *   path="/posts",
     *   tags={"Posts"},
     *   summary="create new post",
     *   operationId="create",
     *   @OA\Parameter(
     *      name="content",
     *      description ="written in HTML",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      ),
     *   ),
     * @OA\Parameter(
     *      name="blog_name",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      ),
     *   ),
     *  @OA\Parameter(
     *      name="state",
     *      description="the state of the post. Specify one of the following: publish, draft, private",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="String"
     *      ),
     *   ),
     *   @OA\Parameter(
     *      name="tags",
     *      description="array of tags ['tag1','tag2']",
     *      in="query",
     *      required=false,
     *   ),
     *   @OA\Parameter(
     *      name="type",
     *      description="type of post (text,photos,videos,audios,quotes",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="String"
     *      ),
     *   ),
     *    @OA\Parameter(
     *      name="source_content",
     *      description="A source for the post content",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *           type="String"
     *      ),
     *   ),
     *   @OA\RequestBody(
     *    required=true,
     *    description="Pass user credentials",
     *    @OA\JsonContent(
     *       required={"content,blog_name,state,type"},
     *       @OA\Property(property="content", type="string", format="text", example="<h1> hello all</h1><br/> <div><p>my name is <span>Ahmed</span></p></div>"),
     *       @OA\Property(property="blog_name", type="string", format="text", example="Ahmed_1"),
     *       @OA\Property(property="type", type="string", example="text"),
     *       @OA\Property(property="state", type="string", format="text", example="private"),
     *       @OA\Property(property="source_content", type="string", format="text", example="www.geeksforgeeks.com"),
     *       @OA\Property(property="tags", type="string", format="text", example="['DFS','BFS']"),
     * 
     * 
     *    ),
     * ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *          response=201,
     *          description="Successfully Created",
     *           @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=201),
     *           @OA\Property(property="msg", type="string", example="Created"),
     *           ),
     *          @OA\Property(property="response", type="object",
     *              @OA\Property(property="post", type="object",
     *                     @OA\Property(property="id", type="integer", example= 123 ),
     *                     @OA\Property(property="content", type="string", format="text", example="<h1> hello all</h1><br/> <div><p>my name is <span>Ahmed</span></p></div>"),
     *                     @OA\Property(property="type", type="string", example="text"),
     *                     @OA\Property(property="state", type="string", format="text", example="private"),
     *                     @OA\Property(property="source_content", type="string", format="text", example="www.geeksforgeeks.com"),
     *                     @OA\Property(property="tags", type="string", format="text", example="['DFS','BFS']"),
     *                     @OA\Property(property="blog_name", type="string", format="text", example="Ahmed_1"),
     *              ),
     *          ),
     *       ),
     *         
     *          
     *       ),
     *)
     **/

    /**
     * This function is responsible for create new post
     *@param $request
     * @return \Illuminate\Http\Response
     */
    public function create(PostRequest $request)
    {
        // get the current authroized user
        $user = Auth::user();
        // get the blog from blogname
        $blog = $this->PostsService->GetBlogData($request->blog_name);
        if (!$blog)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        // check that the user can create post from this Blog
        try {
            $this->authorize('BlogBelongsToUser', $blog);
        } catch (\Throwable $th) {
            return $this->error_response(Errors::ERROR_MSGS_401, '', 401);
        }
        // set blog_id
        $request['blog_id'] = $blog->id;
        // create the date of the post
        $request['date'] = Carbon::now()->toRfc850String();

        // create post
        $post = $this->PostsService->createPost($request->all());
        if (!$post) {
            $error['post'] = 'error while creating post';
            return $this->error_response(Errors::ERROR_MSGS_500, $error, 500);
        }

        // add tags to post
        $postId  = $post->id;
        $postTags = $post->tags;
        $this->PostsService->AddPostTags($postId, $postTags);

        // return post resource 
        return $this->success_response(new PostsResource($post), 201);
    }



    /**
     * @OA\get(
     ** path="/edit/{blog_name}/{post_id}",
     *   tags={"Posts"},
     *   summary="Edit existing Post",
     *   operationId="edit",
     *
     *   @OA\Parameter(
     *      name="post_id",
     *      description="the ID of the post to edit",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="Number"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="blog_name",
     *      description="the blog_name of the post to edit",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *          response=201,
     *          description="Successfully Created",
     *           @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=201),
     *           @OA\Property(property="msg", type="string", example="Created"),
     *           ),
     *          @OA\Property(property="response", type="object",
     *              @OA\Property(property="post", type="object",
     *                     @OA\Property(property="id", type="integer", example= 123 ),
     *                     @OA\Property(property="content", type="string", format="text", example="<h1> hello all</h1><br/> <div><p>my name is <span>Ahmed</span></p></div>"),
     *                     @OA\Property(property="type", type="string", example="text"),
     *                     @OA\Property(property="state", type="string", format="text", example="private"),
     *                     @OA\Property(property="source_content", type="string", format="text", example="www.geeksforgeeks.com"),
     *                     @OA\Property(property="tags", type="string", format="text", example="['DFS','BFS']"),
     *              ),
     *                     @OA\Property(property="blog_name", type="string", format="text", example="Ahmed_1"),
     *                     @OA\Property(property="avatar", type="string", format="text", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),
     *          ),
     *       ),
     *         
     *          
     *       ),
     *)
     **/



    /**
     * @OA\GET(
     ** path="/post/{post-id}",
     *   tags={"Posts"},
     *   summary="fetch a post for editing",
     *   operationId="edit",
     *
     *   @OA\Parameter(
     *      name="content",
     *      description ="written in HTML",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      ),
     *   ),
     * @OA\Parameter(
     *      name="blog_name",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      ),
     *   ),
     *  @OA\Parameter(
     *      name="state",
     *      description="the state of the post. Specify one of the following: publish, draft, private",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="String"
     *      ),
     *   ),
     *   @OA\Parameter(
     *      name="tags",
     *      description="array of tags ['tag1','tag2']",
     *      in="query",
     *      required=false,
     *   ),
     *   @OA\Parameter(
     *      name="type",
     *      description="type of post (text,photos,videos,audios,quotes",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="String"
     *      ),
     *   ),
     *    @OA\Parameter(
     *      name="source_content",
     *      description="A source for the post content",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *           type="String"
     *      ),
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *          response=200,
     *          description="successful post fetching",
     *           @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=200),
     *           @OA\Property(property="msg", type="string", example="OK"),
     *           ),
     *           @OA\Property(property="reponse", type="object",
     *           @OA\Property(property="object_type", type="String", example="post"),
     *           @OA\Property(property="type", type="string", example="text"),
     *           @OA\Property(property="id", type="string", example="2312145464"),
     *           @OA\Property(property="tumbllelog_uuid", type="string", example="yousiflasheen"),
     *           @OA\Property(property="parent_tumnlelog_uuid", type="string", example="john-abdelhamid"),
     *           @OA\Property(property="reblog_key", type="string", example="2312145464"),
     *           @OA\Property(property="trail", type="string", example="[, , ]"),
     *           @OA\Property(property="content", type="string", example="[hello everyboady]"),
     *           @OA\Property(property="layout", type="string", example="[1 ,3]"),
     *           
     *           ),
     *       ),
     *       ),
     *security ={{"bearer":{}}},
     *)
     **/
    /**
     * this function responsible for edit the specified post.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request)
    {
        // get the authenticated user
        $user = Auth::user();

        // get the blog_name and post_id
        $blogName = $request->route('blog_name');
        $postId = $request->route('post_id');

        // get the blog data by blog_name  
        $blog = $this->PostsService->GetBlogData($blogName);
        if (!$blog) {
            $error['blog'] = 'there is no blog with this blog_name';
            return $this->error_response(Errors::ERROR_MSGS_404, $error, 404);
        }

        // get the post data by post_id 
        $post = $this->PostsService->GetPostData($postId);
        if (!$post) {
            $error['post'] = 'there is no post with this id';
            return $this->error_response(Errors::ERROR_MSGS_404, $error, 404);
        }

        // check if this user(blog_name) is authorized to edit this post
        try {
            $this->authorize('EditPost', [$post, $blog]);
        } catch (\Throwable $th) {
            $error['user'] = Errors::AUTHORIZED;
            return $this->error_response(Errors::ERROR_MSGS_401, $error, 401);
        }
        // set up the response        
        return $this->success_response(new PostEditViewResource($post));
    }

    /**
     * @OA\PUT(
     ** path="/update/{blog_name}/{post-id}",
     *   tags={"Posts"},
     *   summary="edit posts with specific id",
     *   operationId="edit",
     *
     *   @OA\Parameter(
     *      name="all request parameters from post creation route are expected",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="object"
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *          response=200,
     *          description="successful post fetching",
     *           @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=200),
     *           @OA\Property(property="msg", type="string", example="OK"),
     *           ),
     *           @OA\Property(property="reponse", type="object",
     *           @OA\Property(property="post_id", type="String", example="1211464646"),
     *           
     *           ),
     *       ),
     *       ),
     *)
     **/
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Posts  $posts
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePostRequest $request)
    {
        $user = Auth::user();
        // get blog_name and post_id parameters
        $blogName = $request->route('blog_name');
        $postId = $request->route('post_id');

        // get the blog data by blog_name  
        $blog = $this->PostsService->GetBlogData($blogName);
        if (!$blog) {
            $error['blog'] = 'there is no blog with this blog_name';
            return $this->error_response(Errors::ERROR_MSGS_404, $error, 404);
        }

        // get the post data by post_id 
        $post = $this->PostsService->GetPostData($postId);
        if (!$post) {
            $error['post'] = 'there is no post with this id';
            return $this->error_response(Errors::ERROR_MSGS_404, $error, 404);
        }
        // check if this user(blog_name) is authorized to edit this post
        try {
            $this->authorize('EditPost', [$post, $blog]);
        } catch (\Throwable $th) {
            $error['user'] = Errors::AUTHORIZED;
            return $this->error_response(Errors::ERROR_MSGS_401, $error, 401);
        }
        // update the date of the post
        $request['date'] = Carbon::now()->toRfc850String();
        // update post with all data
        $isUpdate = $this->PostsService->UpdatePost($post, $request->all());
        // check if the post is updated successfully
        if (!$isUpdate) {
            $error['post'] = 'Failed to update Post';
            return $this->error_response(Errors::ERROR_MSGS_500, $error, 500);
        }
        return $this->success_response('', 200);
    }



    /**
     * @OA\get(
     * path="posts/view/{post_id}",
     * summary="get posts by id",
     * description="User can get posts by id",
     * operationId="postid",
     * tags={"Posts"},
     * @OA\Response(
     *    response=200,
     *    description="Successfully",
     *  @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=200),
     *           @OA\Property(property="msg", type="string", example="success"),
     *           ),
     *          @OA\Property(property="response", type="object",
     *              @OA\Property(property="post", type="object",
     *                     @OA\Property(property="post_id", type="integer", example= 123 ),
     *                     @OA\Property(property="type", type="string", example="text"),
     *                     @OA\Property(property="state", type="string", format="text", example="private"),
     *                     @OA\Property(property="content", type="string", format="text", example="<h1> hello all</h1><br/> <div><p>my name is <span>Ahmed</span></p></div>"),
     *                     @OA\Property(property="date", type="string", format="text", example="Monday, 20-Dec-21 21:54:11 UTC"),
     *                     @OA\Property(property="source_content", type="string", format="text", example="www.geeksforgeeks.com"),
     *                     @OA\Property(property="tags", type="string", format="text", example="['DFS','BFS']"),
     *                     @OA\Property(property="is_liked", type="boolean", example=true),
     *                     @OA\Property(property="is_mine", type="boolean", example=true),
     *              ),
     *              @OA\Property(property="blog", type="object",
     *                     @OA\Property(property="blog_id", type="integer", example= 123 ),
     *                     @OA\Property(property="blog_name", type="string", format="text", example="Ahmed_1"),
     *                     @OA\Property(property="avatar", type="string", format="text", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),
     *                     @OA\Property(property="avatar_shape", type="string", example="circle"),
     *                     @OA\Property(property="replies", type="string", format="text", example="everyone"),
     *                     @OA\Property(property="follower", type="boolean", example=true),
     *              ),
     *          ),
     *       ),
     * ),
     *   @OA\Response(
     *      response=404,
     *       description="Not Found",
     *   ),
     *   @OA\Response(
     *      response=422,
     *       description="invalid Data",
     *   ),
     * )
     */
    /**
     * this function return the post with specific id
     * @param Posts $posts
     * @param int $post_id
     * 
     * @return response
     */
    public function GetPostById(Posts $posts, int $post_id)
    {
        // get post by id
        $post = Posts::find($post_id);
        // check if this id is found
        if (!$post)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);
        return $this->success_response(new PostsResource($post), 200);
    }

    /**
     * @OA\Delete(
     ** path="/post/delete",
     *   tags={"Posts"},
     *   summary="delete existing post",
     *   operationId="destroy",
     *
     *   @OA\Parameter(
     *      name="id",
     *      description="the ID of the post to delete",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="Number"
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *          response=200,
     *          description="successfully deleted",
     *      @OA\JsonContent(
     *            type="object",
     *            @OA\Property(property="Meta", type="object",
     *            @OA\Property(property="Status", type="integer", example=200),
     *            @OA\Property(property="msg", type="string", example="OK"),
     *        ),
     *      ),
     *      
     *    ),
     *  security ={{"bearer":{}}}
     *)
     **/

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Posts  $posts
     * @return \Illuminate\Http\Response
     */
    public function destroy(Posts $posts, int $post_id)
    {
        // get post from id
        $post = Posts::find($post_id);
        if (!$post)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        // get blog of this post
        $blog = $post->BLogs;

        // check if this user(blog_name) is authorized to delete this post
        try {
            $this->authorize('delete', [$post, $blog]);
        } catch (\Throwable $th) {
            return $this->error_response(Errors::ERROR_MSGS_401, '', 401);
        }
        // delte post
        $isDeleted = $this->PostsService->DeletePost($post);
        if (!$isDeleted) {
            $error['post'] = 'error while deleting post';
            return $this->error_response(Errors::ERROR_MSGS_500, $error, 500);
        }

        return $this->success_response('', 200);
    }

    /**
     * @OA\get(
     * path="posts/radar/",
     * summary="get random post",
     * description="User can reset password for existing email",
     * operationId="GetResestPassword",
     * tags={"Posts"},
     * @OA\Response(
     *    response=200,
     *    description="Successfully",
     *  @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=200),
     *           @OA\Property(property="msg", type="string", example="success"),
     *           ),
     *          @OA\Property(property="response", type="object",
     *              @OA\Property(property="post", type="object",
     *                     @OA\Property(property="post_id", type="integer", example= 123 ),
     *                     @OA\Property(property="type", type="string", example="text"),
     *                     @OA\Property(property="state", type="string", format="text", example="private"),
     *                     @OA\Property(property="content", type="string", format="text", example="<h1> hello all</h1><br/> <div><p>my name is <span>Ahmed</span></p></div>"),
     *                     @OA\Property(property="date", type="string", format="text", example="Monday, 20-Dec-21 21:54:11 UTC"),
     *                     @OA\Property(property="source_content", type="string", format="text", example="www.geeksforgeeks.com"),
     *                     @OA\Property(property="tags", type="string", format="text", example="['DFS','BFS']"),
     *                     @OA\Property(property="is_liked", type="boolean", example=true),
     *              ),
     *              @OA\Property(property="blog", type="object",
     *                     @OA\Property(property="blog_id", type="integer", example= 123 ),
     *                     @OA\Property(property="blog_name", type="string", format="text", example="Ahmed_1"),
     *                     @OA\Property(property="avatar", type="string", format="text", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),
     *                     @OA\Property(property="avatar_shape", type="string", example="circle"),
     *                     @OA\Property(property="replies", type="string", format="text", example="everyone"),
     *                     @OA\Property(property="follower", type="boolean", example=true),
     *              ),
     *          ),
     *       ),
     * ),
     *   @OA\Response(
     *      response=404,
     *       description="Not Found",
     *   ),
     *   @OA\Response(
     *      response=422,
     *       description="invalid Data",
     *   ),
     * )
     */

    /**
     * This Function retrieve Post that is not belong to auth user or one of his followers 
     */
    public function GetRadar(Request $request)
    {
        //get auth user
        $user = Auth::user();
        // get blogs of users
        $userBlogs = $user->blogs()->pluck('blog_id');
        // get random post
        $post =  $this->PostsService->GetRandomPost($userBlogs);
        if (!$post)
            return $this->error_response(Errors::ERROR_MSGS_500, 'error get post', 500);
        //retrieve post resource
        return $this->success_response(new PostsResource($post), 200);
    }

    /**
     * This function retrieves the auth user's posts that are saved as drafts
     *
     * @param Request $request
     * @param string $blogName
     * 
     * @return \Illuminate\Http\Response
     * 
     * @author Abdullah Adel
     */
    public function GetDraft(Request $request, string $blogName)
    {
        // get the current authorized user
        $user = Auth::user();

        // get the blog from blogName
        $blog = $this->PostsService->GetBlogData($blogName);
        if (!$blog)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        // check that the blog belongs to the auth user
        try {
            $this->authorize('BlogBelongsToUser', $blog);
        } catch (\Throwable $th) {
            return $this->error_response(Errors::ERROR_MSGS_401, '', 401);
        }

        // get draft posts
        $post = $this->PostsService->GetDraftPosts($blog->id);
        if (!$post)
            return $this->error_response(Errors::ERROR_MSGS_500, 'Error get draft posts', 500);

        //retrieve draft post collection
        return $this->success_response(new DraftPostCollection($post), 200);
    }

    /**
     * This function publishes the draft post of a blog
     *
     * @param Request $request
     * @param string $blogName
     * 
     * @return \Illuminate\Http\Response
     * 
     * @author Abdullah Adel
     */
    public function PublishDraft(Request $request, string $blogName)
    {
        // get the current authorized user
        $user = Auth::user();

        // get the blog from blogName
        $blog = $this->PostsService->GetBlogData($blogName);
        if (!$blog)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        // check that the blog belongs to the auth user
        try {
            $this->authorize('BlogBelongsToUser', $blog);
        } catch (\Throwable $th) {
            return $this->error_response(Errors::ERROR_MSGS_401, '', 401);
        }

        // publish draft post
        $success = $this->PostsService->PublishDraftPost($blog->id, $request->get('post_id'));
        if (!$success)
            return $this->error_response(Errors::ERROR_MSGS_500, 'Error publish draft post', 500);

        //retrieve post collection
        return $this->success_response([]);
    }

    /**
     * @OA\get(
     * path="posts/view/{blog_name}",
     * summary="get posts for blog name",
     * description="get the posts by blog_name",
     * operationId="getblogposts",
     * tags={"Posts"},
     *   @OA\Parameter(
     *      name="blog_name",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     * @OA\Response(
     *    response=200,
     *    description="Successfully",
     *  @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=200),
     *           @OA\Property(property="msg", type="string", example="success"),
     *           ),
     *          @OA\Property(property="response", type="object",
     *          @OA\Property(property="posts", type="array",
     *            @OA\Items(
     *              @OA\Property(property="post", type="object",
     *                     @OA\Property(property="post_id", type="integer", example= 123 ),
     *                     @OA\Property(property="type", type="string", example="text"),
     *                     @OA\Property(property="state", type="string", format="text", example="private"),
     *                     @OA\Property(property="content", type="string", format="text", example="<h1> hello all</h1><br/> <div><p>my name is <span>Ahmed</span></p></div>"),
     *                     @OA\Property(property="date", type="string", format="text", example="Monday, 20-Dec-21 21:54:11 UTC"),
     *                     @OA\Property(property="source_content", type="string", format="text", example="www.geeksforgeeks.com"),
     *                     @OA\Property(property="tags", type="string", format="text", example="['DFS','BFS']"),
     *                     @OA\Property(property="is_liked", type="boolean", example=true),
     *              ),
     *              @OA\Property(property="blog", type="object",
     *                     @OA\Property(property="blog_id", type="integer", example= 123 ),
     *                     @OA\Property(property="blog_name", type="string", format="text", example="Ahmed_1"),
     *                     @OA\Property(property="avatar", type="string", format="text", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),
     *                     @OA\Property(property="avatar_shape", type="string", example="circle"),
     *                     @OA\Property(property="replies", type="string", format="text", example="everyone"),
     *                     @OA\Property(property="follower", type="boolean", example=true),
     *              ),
     *             ),
     *          ),
     *          @OA\Property(property="next_url", type="string", example= "http://127.0.0.1:8000/api/user/followings?page=2" ),
     *          @OA\Property(property="total", type="integer", example= 20 ),
     *          @OA\Property(property="current_page", type="integer", example= 1 ),
     *          @OA\Property(property="posts_per_page", type="integer", example=4),
     *          ),
     *       ),
     * ),
     *   @OA\Response(
     *      response=404,
     *       description="Not Found",
     *   ),
     *   @OA\Response(
     *      response=422,
     *       description="invalid Data",
     *   ),
     * )
     */
    public function GetBlogPosts(Request $request, $blog_name)
    {
        // get blog
        $blog =  $this->PostsService->GetBlogByName($blog_name);

        if (!$blog)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);
        // get posts of the blog depend on requested user
        $posts = $this->PostsService->GetPostsOfBlog($blog->id);
        return $this->success_response(new PostsCollection($posts));
    }

    /**
     * @OA\Get(
     *   path="/recommended/posts",
     *   summary="Retrieve recommended posts",
     *   description="Retrieve recommended posts for the explore",
     *   operationId="GetRecommendedTags",
     *   tags={"Explore"},
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *     @OA\JsonContent(
     *       @OA\Property(property="meta", type="object",
     *         @OA\Property(property="status_code", type="integer", example=200),
     *         @OA\Property(property="msg", type="string", example="Success"),
     *       ),
     *       @OA\Property(property="response", type="object",
     *         @OA\Property(property="post", type="array",
     *           @OA\Items(type="object",
     *              @OA\Property(property="post", type="object",
     *                  @OA\Property(property="post_id", type="integer", example=1),
     *                  @OA\Property(property="type", type="string", example="text"),
     *                  @OA\Property(property="state", type="string", example="publish"),
     *                  @OA\Property(property="title", type="string", example="Happy New Year"),
     *                  @OA\Property(property="content", type="string", example="<h1>And I declare it's too bad.</h1>"),
     *                  @OA\Property(property="date", type="string", example="Saturday, 01-Jan-22 16:45:14 UTC"),
     *                  @OA\Property(property="source_content", type="string", example="happynewyear"),
     *                  @OA\Property(property="is_liked", type="boolean", example=false),
     *                  @OA\Property(property="is_mine", type="boolean", example=false),
     *                  @OA\Property(property="notes_count", type="boolean", example=14),
     *                  @OA\Property(property="tags", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="tag_name", type="string", example="asperiores"),
     *                      )
     *                  )
     *              ),
     *              @OA\Property(property="blog", type="object",
     *                  @OA\Property(property="blog_id", type="integer", example=1),
     *                  @OA\Property(property="blog_name", type="string", example="abdullahadel"),
     *                  @OA\Property(property="avatar", type="string", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),
     *                  @OA\Property(property="avatar_shape", type="string", example="circle"),
     *                  @OA\Property(property="replies", type="string", example="everyone"),
     *                  @OA\Property(property="follower", type="boolean", example=false),
     *              ),
     *           )
     *         ),
     *         @OA\Property(property="next_url", type="string", example="https://www.cmplr.tech/api/recommended/posts?page=2"),
     *         @OA\Property(property="total", type="number", example=101),
     *         @OA\Property(property="current_page", type="number", example=1),
     *         @OA\Property(property="posts_per_page", type="number", example=15),
     *       )
     *     )
     *   ),
     * security ={{"bearer":{}}}
     * )
     */

    /**
     * This function is responsible for getting
     * recommended posts (paginated)
     * 
     * @return \Illuminate\Http\Response
     * 
     * @author Abdullah Adel
     */
    public function GetRecommendedPosts()
    {
        // Check if there is an authenticated user
        $user = auth('api')->user();
        $user_id = null;

        if ($user) {
            $user_id = $user->id;
        }

        $recommended_posts = $this->PostsService->GetRandomPosts($user_id);

        if (!$recommended_posts) {
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);
        }

        $response = $this->success_response(new PostsCollection($recommended_posts));

        return $response;
    }

    /**
     * @OA\Get(
     *   path="/trending/posts",
     *   summary="Retrieve trending posts",
     *   description="Retrieve trending posts for the explore",
     *   operationId="GetTrendingTags",
     *   tags={"Explore"},
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *     @OA\JsonContent(
     *       @OA\Property(property="meta", type="object",
     *         @OA\Property(property="status_code", type="integer", example=200),
     *         @OA\Property(property="msg", type="string", example="Success"),
     *       ),
     *       @OA\Property(property="response", type="object",
     *         @OA\Property(property="post", type="array",
     *           @OA\Items(type="object",
     *              @OA\Property(property="post", type="object",
     *                  @OA\Property(property="post_id", type="integer", example=1),
     *                  @OA\Property(property="type", type="string", example="text"),
     *                  @OA\Property(property="state", type="string", example="publish"),
     *                  @OA\Property(property="title", type="string", example="Happy New Year"),
     *                  @OA\Property(property="content", type="string", example="<h1>And I declare it's too bad.</h1>"),
     *                  @OA\Property(property="date", type="string", example="Saturday, 01-Jan-22 16:45:14 UTC"),
     *                  @OA\Property(property="source_content", type="string", example="happynewyear"),
     *                  @OA\Property(property="is_liked", type="boolean", example=false),
     *                  @OA\Property(property="is_mine", type="boolean", example=false),
     *                  @OA\Property(property="notes_count", type="boolean", example=14),
     *                  @OA\Property(property="tags", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="tag_name", type="string", example="asperiores"),
     *                      )
     *                  )
     *              ),
     *              @OA\Property(property="blog", type="object",
     *                  @OA\Property(property="blog_id", type="integer", example=1),
     *                  @OA\Property(property="blog_name", type="string", example="abdullahadel"),
     *                  @OA\Property(property="avatar", type="string", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),
     *                  @OA\Property(property="avatar_shape", type="string", example="circle"),
     *                  @OA\Property(property="replies", type="string", example="everyone"),
     *                  @OA\Property(property="follower", type="boolean", example=false),
     *              ),
     *           )
     *         ),
     *         @OA\Property(property="next_url", type="string", example="https://www.cmplr.tech/api/trending/posts?page=2"),
     *         @OA\Property(property="total", type="number", example=101),
     *         @OA\Property(property="current_page", type="number", example=1),
     *         @OA\Property(property="posts_per_page", type="number", example=15),
     *       )
     *     )
     *   ),
     * security ={{"bearer":{}}}
     * )
     */

    /**
     * This function is responsible for getting
     * trending posts (paginated)
     * 
     * @return \Illuminate\Http\Response
     * 
     * @author Abdullah Adel
     */
    public function GetTrendingPosts()
    {
        // Check if there is an authenticated user
        $user = auth('api')->user();
        $user_id = null;

        if ($user) {
            $user_id = $user->id;
        }

        $trending_posts = $this->PostsService->GetRandomPosts($user_id);

        if (!$trending_posts) {
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);
        }

        $response = $this->success_response(new PostsCollection($trending_posts));

        return $response;
    }

    /**
     * @OA\GET(
     * path="MiniProfileView/{blog_id}",
     * summary="MiniProfileView of blog",
     * description="This method can be used to  upload image",
     * operationId="miniprofile",
     * tags={"profile"},
     * @OA\RequestBody(
     *      required=true,
     *      description="Pass user credentials",
     *      @OA\JsonContent(
     *           @OA\Property(property="blog_id", type="integer",example=25),
     *      ),
     *    ),
     * @OA\Response(
     *    response=404,
     *    description="Not Found",
     * ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     * @OA\Response(
     *    response=200,
     *    description="success",
     *    @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="Meta", type="object",
     *          @OA\Property(property="Status", type="integer", example=200),
     *           @OA\Property(property="msg", type="string", example="OK"),
     *        ),
     *       @OA\Property(property="response", type="object",
     *       @OA\Property(property="blog", type="object",
     *             @OA\Property(property="blog_name", type="string", example="ahmed1"),
     *             @OA\Property(property="avatar", type="string", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),   
     *             @OA\Property(property="title", type="string", example="title"),   
     *             @OA\Property(property="header_image", type="string",example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),   
     *             @OA\Property(property="is_primary", type="boolean",example=true),   
     *             @OA\Property(property="description", type="string",example="hello"),   
     *             @OA\Property(property="is_followed", type="boolean",example=false),   
     *             @OA\Property(property="is_blocked", type="boolean",example=false),   
     * ),                                         
     *             @OA\Property(property="views", type="array",
     *                @OA\Items(
     *                      @OA\Property(
     *                         property="url",
     *                         type="string",
     *                         example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"
     *                      ),
     *                      @OA\Property(
     *                         property="post_id",
     *                         type="integer",
     *                         example=  25
     *                      ),
     *                  ),
     *                ),           
     *           ),
     *        ),
     *     ),
     * security ={{"bearer":{}}},
     * )
     */

    /**
     * This Function Responisble for 
     * get miniview of certain blog by show 3 of its blogs
     * 
     * @param int $blog_id
     * @return response
     */
    public function MiniProfileView(Request $request, int $blog_id)
    {
        // get blog by blog_id
        $blog = Blog::find($blog_id);
        if (!$blog)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        // get post data
        $posts = $this->PostsService->MiniViewPostData($blog_id);
        if (!$posts)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        // set blog data
        $response['blog'] = $this->PostsService->MiniViewBlogData($blog);

        //get views
        $response['views'] = $this->PostsService->GetViews($posts);

        return $this->success_response($response);
    }


    /**
     * @OA\Get(
     * path="/profile/likes/{blog_name}",
     * summary="Retrieve a profile's Likes",
     * description="retrieve the posts liked by the user",
     * operationId="getprofileLikes",
     * tags={"profile"},
     *
     *
     * @OA\Response(
     *    response=404,
     *    description="Not Found",
     * ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   
     *       @OA\Response
     *		(
     *	    	response=200,
     *    		description="success",
     *    		@OA\JsonContent
     *			(
     *       			type="object",
     *       			@OA\Property
     *				    (
     *					    property="Meta", type="object",
     *					    @OA\Property(property="Status", type="integer", example=200),
     *					    @OA\Property(property="msg", type="string", example="OK"),
     *        			),
     *
     *       			@OA\Property
     *				    (
     *					    property="response", type="object",
     *             			@OA\Property(property="total_posts", type="Number", example=263),
     *             			@OA\Property
     *					    (
     *						    property="posts", type="array",
     *                			@OA\Items
     *						    (
     *			        	        @OA\Property(property="post1",description="the first post",type="object"),
     *			        	        @OA\Property(property="post2",description="the second post",type="object"),
     *			        	        @OA\Property(property="post3",description="the third post",type="object"),
     *			        	    ),
     *       
     *               		),
     *           		),
     *        		),
     *     	),
     * security ={{"bearer":{}}}
     * )
     */

    /**
     * this function is responsible for get profile likes for blog
     * @param Request $request
     * @param string $blog_name
     * @return Response
     */
    public function ProfileLikes(Request $request, string $blog_name)
    {
        $blog = Blog::where('blog_name', $blog_name)->first();
        if ($blog == null)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        $user = BlogUser::where('blog_id', $blog->id)->where('primary', true)->first();
        if (!$user)
            return $this->error_response(Errors::ERROR_MSGS_404, 'it is not primary blog', 404);

        // get id of all posts liked by user
        $likes = $this->UserService->GetLikes($user->id);

        //get liked posts
        $posts = Posts::whereIn('id', $likes)->paginate(config::PAGINATION_LIMIT);

        return $this->success_response(new PostsCollection($posts));
    }


    /**
     *	@OA\Get
     *	(
     * 		path="profile/following/{blog_name}",
     * 		summary="get followings of profile(user)",
     * 		description="Retrieve following blogs for User.",
     * 		operationId="Retrieve profile followings",
     * 		tags={"profile"},
     * @OA\Response(
     *    response=200,
     *    description="Successfully",
     *  @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="Meta", type="object",
     *           @OA\Property(property="Status", type="integer", example=200),
     *           @OA\Property(property="msg", type="string", example="success"),
     *           ),
     *          @OA\Property(property="response", type="object",
     *          @OA\Property(property="blogs", type="array",
     *            @OA\Items(
     *              @OA\Property(property="post", type="object",
     *                     @OA\Property(property="blog_id", type="integer", example= 123 ),
     *                     @OA\Property(property="blog_name", type="string", example="ahmed"),
     *                     @OA\Property(property="title", type="string", format="text", example="CMP"),
     *                     @OA\Property(property="avatar", type="string", format="text", example="https://assets.tumblr.com/images/default_avatar/cone_closed_128.png"),
     *                     @OA\Property(property="avatar_shape", type="string", format="text", example="Circle"),
     *                     @OA\Property(property="description", type="string", format="text", example="ahmed217"),
     *              ),
     *             ),
     *          ),
     *          @OA\Property(property="next_url", type="string", example= "http://127.0.0.1:8000/api/user/followings?page=2" ),
     *          @OA\Property(property="total", type="integer", example= 20 ),
     *          @OA\Property(property="current_page", type="integer", example= 1 ),
     *          @OA\Property(property="posts_per_page", type="integer", example=4),
     *          ),
     *       ),
     * ),
     *   @OA\Response(
     *      response=404,
     *       description="Not Found",
     *   ),
     *   @OA\Response(
     *      response=422,
     *       description="invalid Data",
     *   ),
     * security ={{"bearer":{}}}
     * )
     */
    /**
     * this function is responsible for get profile likes for blog
     * @param Request $request
     * @param string $blog_name
     * @return Response
     */
    public function ProfileFollowing(Request $request, string $blog_name)
    {
        $blog = Blog::where('blog_name', $blog_name)->first();
        if ($blog == null)
            return $this->error_response(Errors::ERROR_MSGS_404, '', 404);

        $user = BlogUser::where('blog_id', $blog->id)->where('primary', true)->first();
        if (!$user)
            return $this->error_response(Errors::ERROR_MSGS_404, 'it is not primary blog', 404);

        $blogsIds = Follow::where('user_id', $user->id)->pluck('blog_id');
        $blogs = Blog::whereIn('id', $blogsIds)->paginate(config::API_PAGINATION_LIMIT);
        return $this->success_response(new BlogCollection($blogs));
    }

    /**
     *	@OA\Get
     *	(
     * 		path="post/tagged",
     * 		summary="Get Posts with Tag",
     * 		description="retrieve the posts with specific tag",
     * 		operationId="GetTaggedPosts",
     * 		tags={"Posts"},
     *
     *    	@OA\Parameter
     *		(
     *      		name="tag",
     *      		description="The tag on the posts you'd like to retrieve",
     *      		in="path",
     *      		required=true,
     *      		@OA\Schema
     *			(
     *           		type="String"
     *      		)
     *   	),
     *
     *    	@OA\Parameter
     *		(
     *			name="before",
     *			description="The timestamp of when you'd like to see posts before.",
     *			in="query",
     *			required=false,
     *		    @OA\Schema
     *		 	(
     *		           type="integer"
     *			)
     *   	),
     *
     *   	@OA\Parameter
     *		(
     *      		name="limit",
     *      		description="the number of posts to return",
     *      		in="query",
     *      		required=false,
     *      		@OA\Schema
     *			(
     *           		type="Number"
     *      		)
     *   	),
     *
     *    	@OA\Parameter
     *		(
     *			name="filter",
     *			description="Specifies the post format to return, other than HTML: text – Plain text, no HTML; raw – As entered by the user (no post-processing)",
     *			in="query",
     *			required=false,
     *		    @OA\Schema
     *		 	(
     *		           type="String"
     *			)
     *   	),
     *    
     *    	@OA\RequestBody
     *		(
     *      		required=true,
     *      		description="Pass user credentials",
     *      		@OA\JsonContent
     *			(
     *	    		required={"tag"},
     *      			@OA\Property(property="tag", type="String", format="text", example="anime"),
     *      			@OA\Property(property="before", type="integer", format="integer", example=10),
     *      			@OA\Property(property="limit", type="integer", format="integer", example=1),
     *      			@OA\Property(property="filter", type="String", format="text", example="HTML"),
     *      		),
     *    	),
     *
     * 		@OA\Response
     *		(
     *    		response=404,
     *    		description="Not Found",
     * 		),
     *
     *	   	@OA\Response
     *		(
     *		      response=401,
     *		      description="Unauthenticated"
     *	   	),
     *
     *		@OA\Response
     *		(
     *	    	response=200,
     *    		description="success",
     *    		@OA\JsonContent
     *			(
     *       			type="object",
     *       			@OA\Property
     *				    (
     *					    property="Meta", type="object",
     *					    @OA\Property(property="Status", type="integer", example=200),
     *					    @OA\Property(property="msg", type="string", example="OK"),
     *        			),
     *
     *       			@OA\Property
     *				    (
     *					    property="response", type="object",
     *             			@OA\Property(property="blog", type="object"),
     *             			@OA\Property
     *					    (
     *						    property="posts", type="array",
     *                			@OA\Items
     *						    (
     *			        	        @OA\Property(property="post1",description="the first post",type="object"),
     *			        	        @OA\Property(property="post2",description="the second post",type="object"),
     *			        	        @OA\Property(property="post3",description="the third post",type="object"),
     *			        	    ),
     *       
     *               		),
     *					    @OA\Property(property="total_posts", type="integer", example=3),
     *           		),
     *        		),
     *     	)
     * )
     */
    public function GetTaggedPosts(Request $request)
    {
        $tag = $request->tag;

        // getting all user follows this tag 
        $posts = $this->PostsService->GetPostsWithTag($tag);

        return response()->json(new TaggedPostsCollection($posts), 200);
    }
}