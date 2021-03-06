<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\StatusPipeline\NewStatusPipeline;
use Auth, Hashids;
use App\{Comment, Profile, Status};

class CommentController extends Controller
{

    public function show(Request $request, $username, int $id)
    {
      $user = Profile::whereUsername($username)->firstOrFail();
      $status = Status::whereProfileId($user->id)->whereNotNull('in_reply_to_id')->findOrFail($id);
      return view('status.reply', compact('user', 'status'));
    }

    public function store(Request $request)
    {
      if(Auth::check() === false) { abort(403); }
      $this->validate($request, [
        'item'    => 'required|integer',
        'comment' => 'required|string|max:500'
      ]);
      $comment = $request->input('comment');
      $statusId = $request->item;

      $user = Auth::user();
      $profile = $user->profile;
      $status = Status::findOrFail($statusId);

      $reply = new Status();
      $reply->profile_id = $profile->id;
      $reply->caption = $comment;
      $reply->rendered = e($comment);
      $reply->in_reply_to_id = $status->id;
      $reply->in_reply_to_profile_id = $status->profile_id;
      $reply->save();

      NewStatusPipeline::dispatch($reply, false);

      if($request->ajax()) {
        $response = ['code' => 200, 'msg' => 'Comment saved', 'username' => $profile->username, 'url' => $reply->url(), 'profile' => $profile->url()];
      } else {
        $response = redirect($status->url());
      }

      return $response;
    }
}
