<?php

namespace Servidor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Servidor\Exceptions\System\UserNotFoundException;
use Servidor\Http\Requests\CreateSite;
use Servidor\Http\Requests\UpdateSite;
use Servidor\Site;
use Servidor\System\User as SystemUser;
use Servidor\System\Users\LinuxUser;

class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        /**
         * @var \Illuminate\Contracts\Support\Arrayable
         */
        $sites = Site::all();

        return response()->json($sites->toArray());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateSite $request): JsonResponse
    {
        $site = Site::create($request->validated());

        return response()->json($site, Response::HTTP_CREATED);
    }

    /**
     * Display a list of branches on the given site's repository.
     */
    public function branches(Request $request, Site $site): JsonResponse
    {
        $cmd = "git ls-remote --heads '%s' | sed 's^.*refs/heads/^^'";
        $repo = $request->query('repo', $site->source_repo);
        if (!$repo || !is_string($repo)) {
            throw ValidationException::withMessages(['repo' => 'Missing repo and site does not have one set.']);
        }

        exec(sprintf($cmd, $repo), $branches);

        return response()->json($branches);
    }

    /**
     * Pull the latest commit from Git.
     */
    public function pull(Site $site): JsonResponse
    {
        $root = $site->project_root;
        $branch = $site->source_branch;

        if (!$site->type || 'redirect' == $site->type) {
            $error = 'Project type does not support pull.';
        } elseif (!$root) {
            $error = 'Project is missing its document root!';
        }

        if (isset($error)) {
            return response()->json(compact('error'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (is_dir($root . '/.git')) {
            $args = $branch ? ' && git checkout "' . $branch . '"' : '';

            exec('cd "' . $root . '"' . $args . ' && git pull');

            return response()->json($site, Response::HTTP_OK);
        }

        if (!is_dir($root)) {
            mkdir($root);
        }

        $args = $branch ? ' --branch "' . $branch . '"' : '';
        $paths = ' "' . $site->source_repo . '" "' . $root . '"';

        $cmd = 'git clone' . $args . $paths;

        exec($cmd);

        return response()->json($site, Response::HTTP_OK);
    }

    public function showLog(Site $site, string $log): Response
    {
        return response()->make($site->readLog($log));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSite $request, Site $site): JsonResponse
    {
        $site->update($request->validated());

        if (true === $request->input('create_user')) {
            $username = Str::slug($site->name);

            try {
                SystemUser::findByName($username);
            } catch (UserNotFoundException $e) {
                $user = new LinuxUser(['name' => $username]);

                $user->setCreateHome(true);

                if ($user = SystemUser::createCustom($user)) {
                    $site->system_user = $user['uid'];
                    $site->save();
                }
            }
        }

        return response()->json($site, Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    public function destroy(Site $site)
    {
        $site->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
