<?php

namespace App\Repositories;

use App\Discussion;
use App\Scopes\StatusScope;

class DiscussionRepository
{
    use BaseRepository;

    protected $model;

    public function __construct(Discussion $discussion)
    {
        $this->model = $discussion;
    }

    /**
     * Get number of the records.
     *
     * @param  Request $request
     * @param  integer $number
     * @param  string $sort
     * @param  string $sortColumn
     * @return collection
     */
    public function pageWithRequest($request, $number = 10, $sort = 'desc', $sortColumn = 'created_at')
    {
        $this->model = $this->checkAuthScope();

        $keyword = $request->get('keyword');

        return $this->model
            ->when($keyword, function ($query) use ($keyword) {
                $query->where('title', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($query) use ($keyword) {
                        $query->where('name', 'like', "%{$keyword}%");
                    });
            })
            ->orderBy($sortColumn, $sort)->paginate($number);
    }

    /**
     * Get number of the records.
     *
     * @param  int $number
     * @param  string $sort
     * @param  string $sortColumn
     * @return Paginate
     */
    public function page($number = 10, $sort = 'asc', $sortColumn = 'created_at')
    {
        $this->model = $this->checkAuthScope();

        return $this->model->with(['user', 'tags'])->withCount('comments')->orderBy($sortColumn, $sort)->paginate($number);
    }

    /**
     * Get the discussion record without draft scope.
     *
     * @param  int $id
     * @return mixed
     */
    public function getById($id)
    {
        $this->model = $this->checkAuthScope();

        return $this->model->withCount('comments')->findOrFail($id);
    }

    /**
     * Store a new discussion.
     * @param  array $data
     * @return Model
     */
    public function store($data)
    {
        $discussion = $this->model->create($data);

        if (is_array($data['tags'])) {
            $this->syncTag($discussion, $data['tags']);
        } else {
            $this->syncTag($discussion, json_decode($data['tags']));
        }

        return $discussion;
    }

    /**
     * Update a record by id.
     *
     * @param  int $id
     * @param  array $data
     * @return boolean
     */
    public function update(int $id, array $data)
    {
        $this->model = $this->checkAuthScope();

        $discussion = $this->model->findOrFail($id);

        if (is_array($data['tags'])) {
            $this->syncTag($discussion, $data['tags']);
        } else {
            $this->syncTag($discussion, json_decode($data['tags']));
        }

        return $discussion->update($data);
    }

    /**
     * Update a record by id without tag.
     *
     * @param  int $id
     * @param  array $data
     * @return boolean
     */
    public function updateWithoutTags(int $id, array $data)
    {
        $this->model = $this->checkAuthScope();

        $discussion = $this->model->findOrFail($id);

        return $discussion->update($data);
    }

    /**
     * Check the auth and the model without global scope when user is the admin.
     *
     * @return Model
     */
    public function checkAuthScope()
    {
        if (auth()->check() && auth()->user()->is_admin) {
            $this->model = $this->model->withoutGlobalScope(StatusScope::class);
        }

        return $this->model;
    }

    /**
     * Sync the tags for the discussion.
     *
     * @param  int $number
     * @return Paginate
     */
    public function syncTag(Discussion $discussion, array $tags)
    {
        return $discussion->tags()->sync($tags);
    }

    /**
     * Get a list of tag ids that are associated with the given discussion.
     *
     * @param \App\Discussion $discussion
     *
     * @return array
     */
    public function listTagsIdsForDiscussion(Discussion $discussion)
    {
        return $discussion->tags->pluck('id')->toArray();
    }

    /**
     * Delete the draft article.
     *
     * @param int $id
     * @return boolean
     */
    public function destroy($id)
    {
        return $this->getById($id)->delete();
    }

    public function getRelatedDiscussions($discussion)
    {
        $discussions = $this->model->whereHas('tags', function ($q) use ($discussion) {
            return $q->whereIn('tag', $discussion->tags->pluck('tag')); 
        })
        ->where('id', '!=', $discussion->id)
        ->get();

        return $discussions;
    }

}
