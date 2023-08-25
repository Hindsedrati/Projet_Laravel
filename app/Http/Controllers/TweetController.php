<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

use App\Models\Analytic;
use App\Models\File;
use App\Models\Follow;
use App\Models\Like;
use App\Models\User;
use App\Models\Tweet;

class TweetController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function dashboard(): View
    {
        $tweets = Tweet::where('comments', null)
            ->with(['user', 'Likes'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        if (Auth::id()) {
            foreach ($tweets as $tweet)
            {
                if (!$tweet->AnalyticsBy(Auth::guard('user')->user()))
                {
                    $tweet->Analytics()->create([
                        'user_id' => Auth::guard('user')->user()->id,
                    ]);
                }
            }
        }

        foreach ($tweets as $tweet)
        {
            $tweet->tweet = $this->hashtag_links($tweet->tweet);
        }

        // return view('dashboard', [ 'tweets' => $tweets, 'hashtags' => $hashtags ]);
        return $this->renderView('tweet.main', [ 'tweets' => $tweets ]);
    }

    /**
     * Store a newsly created tweet in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Facades\Redirect
     */
    public function addTweet(Request $request): RedirectResponse
    {
        $request->validate([
            'tweet' => [ 'required', 'string', 'max:144']
        ], [
            'tweet.required' => 'Veuillez entrer votre tweet',
            'tweet.string' => 'Veuillez entrer une valeur valide',
            'tweet.max' => 'Votre tweet ne doit pas dépasser 144 caractères',
        ]);

        $file = null;
        $extension = null;
        $path = '';

        $tweet = new Tweet;

        $tweet->uuid = Str::ulid();
        $tweet->user_id = Auth::guard('user')->user()->id;
        $tweet->handle = '';
        $tweet->tweet = $request->input('tweet');

        $tweet->save();

        if(!empty($request->input('files')))
        {
            foreach ($request->input('files') as $key => $file) {
                if(!empty($file))
                {
                    $newpath = Storage::move('tmp/'.$file, 'public/uploads/'.$file);

                    $newfile = new File;
                    $newfile->user_id = auth()->user()->id;
                    $newfile->tweet_uuid = $tweet->uuid;
                    $newfile->path = $file;

                    $newfile->save();
                }
            }
        }

        // return Redirect::route('tweet.dashboard');
        return redirect()->back();
    }

    /**
     * Store a newly created tweet comment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Facades\Redirect
     */
    public function addTweetComment(Tweet $tweet, Request $request): RedirectResponse
    {
        // $tweet = Tweet::where('uuid', $request->uuid)->first();

        if (!$tweet) {
            return back()->withErrors([
                'tweet' => 'Tweet introuvable',
            ]);
        }

        $request->validate([
            'tweet' => 'required|string|max:144',
        ], [
            'tweet.required' => 'Veuillez entrer votre tweet',
            'tweet.string' => 'Veuillez entrer une valeur valide',
            'tweet.max' => 'Votre tweet ne doit pas dépasser 144 caractères',
        ]);

        $file = null;
        $extension = null;
        $path = '';

        $tweetComment = new Tweet;

        $tweetComment->uuid = Str::ulid();
        $tweetComment->user_id = Auth::guard('user')->user()->id;
        $tweetComment->handle = '';
        $tweetComment->tweet = $request->input('tweet');
        $tweetComment->comments = $tweet->uuid;

        $tweetComment->save();

        if(!empty($request->input('files')))
        {
            foreach ($request->input('files') as $key => $file) {
                if(!empty($file))
                {
                    $newpath = Storage::move('tmp/'.$file, 'public/uploads/'.$file);

                    $newfile = new File;
                    $newfile->user_id = auth()->user()->id;
                    $newfile->tweet_uuid = $tweetComment->uuid;
                    $newfile->path = $file;

                    $newfile->save();
                }
            }
        }

        // return Redirect::route('tweet.comments', $tweet->uuid);
        return back();
    }

    /**
     * Display tweet retweet
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function tweetRetweetView(Request $request): View
    {
        $tweet = Tweet::where('uuid', $request->uuid)->first();

        $tweet->tweet = $this->hashtag_links($tweet->tweet);

        // return view('retweet', [ 'tweet' => $tweet, 'hashtags' => $hashtags ]);
        return $this->renderView('tweet.retweet', [ 'tweet' => $tweet ]);
    }

    /**
     * Store a newly created tweet retweet in storage.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Facades\Redirect
     */
    public function addTweetRetweet(Tweet $tweet, Request $request): RedirectResponse
    {
        // $tweet = Tweet::where('uuid', $request->uuid)->first();

        if (!$tweet) {
            abort(403);
        }

        $file = null;
        $extension = null;
        $path = '';

        $tweetRetweet = new Tweet;

        $tweetRetweet->uuid = Str::ulid();
        $tweetRetweet->user_id = Auth::guard('user')->user()->id;
        $tweetRetweet->handle = '';
        $tweetRetweet->tweet = $request->tweet;

        $tweetRetweet->Retweets = $tweet->uuid;

        $tweetRetweet->save();

        if(!empty($request->input('files')))
        {
            foreach ($request->input('files') as $key => $file) {
                if(!empty($file))
                {
                    $newpath = Storage::move('tmp/'.$file, 'public/uploads/'.$file);

                    $newfile = new File;
                    $newfile->user_id = auth()->user()->id;
                    $newfile->tweet_uuid = $tweet->uuid;
                    $newfile->path = $file;

                    $newfile->save();
                }
            }
        }

        // return Redirect::route('tweet.dashboard');
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $uuid
     * @return \Illuminate\Support\Facades\Redirect
     */
    public function tweetDestroy(Request $request): RedirectResponse
    {
        $tweet = Tweet::where('uuid', $request->uuid)->firstOrFail();

        if ($tweet->comment) {
            if (Auth::guard('user')->user()->id === $tweet->comment->user_id) {
                if ($tweet->files) {
                    foreach ($tweet->files as $key => $file) {
                        Storage::delete('public/uploads/'.$file->path);
                    }
                }

                $tweet->tweet = 'Ce tweet a été masqué.';
                $tweet->save();
                
                return back();
            }
        }

        if (Auth::guard('user')->user()->id === $tweet->user_id) {
            if ($tweet->files) {
                foreach ($tweet->files as $key => $file) {
                    Storage::delete('public/uploads/'.$file->path);
                }
            }

            $tweet->delete();

            return redirect()->route('tweet.dashboard');
        }

        abort(403);
    }

    /**
     * 
     */
    public function addTweetSignale(Request $request): RedirectResponse
    {
        echo 'addTweetSignale';
        die();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $uuid
     * @return \Illuminate\Support\Facades\Redirect
     */
    public function tweetDestroyModo(Request $request): RedirectResponse
    {
        $tweet = Tweet::where('uuid', $request->uuid)->firstOrFail();

        if ($tweet->files) {
            foreach ($tweet->files as $key => $file) {
                Storage::delete('public/uploads/'.$file->path);
            }
        }

        $tweet->tweet = 'Ce tweet a été supprimé par un modérateur';
        $tweet->save();

        return back();
    }

    /**
     * Display tweet comments
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function tweetCommentsView(Request $request): View
    {
        $tweet = Tweet::query()->where('uuid', $request->uuid)->firstOrFail();

        $tweet->tweet = $this->hashtag_links($tweet->tweet);

        $comments = Tweet::query()->where('comments', $request->uuid)->with(['user', 'Likes'])
            ->orderBy('id', 'desc')
            ->paginate(10);
        
        foreach($comments as $comment)
        {
            $comment->tweet = $this->hashtag_links($comment->tweet);
        }

        return $this->renderView('tweet.comments', [ 'tweet' => $tweet, 'comments' => $comments ]);
    }
}
