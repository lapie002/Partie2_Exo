<?php

namespace App\Repositories;

use Illuminate\Contracts\Auth\Guard;

use App\Models\Poll, App\Models\Answer;

class PollRepository
{

	/**
	 * Instance de Guard.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * Crée une nouvelle instance de PollRepository
	 *
	 * @param Illuminate\Contracts\Auth\Guard $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Enregistrement des réponses
	 *
	 * @param  App\Http\Requests\PollUpdateRequest $request;
	 * @param  App\Models\Poll $poll
	 * @return void
	 */
	private function saveAnswers($request, $poll)
	{
		$answers = [];

		foreach ($request->input('answers') as $value) 
		{
			array_push($answers, new Answer(['answer' => $value]));
		}	

		$poll->answers()->saveMany($answers);
	}

	/**
	 * Récupération des sondages existants
	 *
	 * @param  integer $n
	 * @return array
	 */
	public function getPaginate($n)
	{
		$polls = Poll::paginate($n);
		$polls_voted = Poll::has('users')->lists('question')->all();

		return compact('polls', 'polls_voted');
	}

	/**
	 * Enregistrement d'un sondage
	 *
	 * @param  App\Http\Requests\PollUpdateRequest $request;
	 * @return void
	 */
	public function store($request)
	{
		// Création du sondage
		$poll = Poll::create(['question' => $request->input('question')]);

		// Création des réponses
		$this->saveAnswers($request, $poll);
	}

	/**
	 * Récupération des informations d'un sondage pour affichage
	 *
	 * @return array
	 */
	public function getPollWithAnswersAndDone($id)
	{
		$poll = Poll::with('answers')->find($id);
		$total = $poll->answers()->sum('result');

		// Si utilisateur authentifié on vérifie s'il a déjà voté
		if($this->auth->check()) 
		{
			$done = $this->auth->user()->hasVoted($id);
		} 

		return compact('poll', 'total', 'done');
	}

	/**
	 * Récupération des informations d'un sondage pour modification
	 *
	 * @param  integer $id
	 * @return mixed
	 */
	public function getPollWithAnswers($id)
	{
		$poll = Poll::with('answers')->find($id);

		return compact('poll');
	}

	/**
	 * Teste qu'un sondage a déjà reçu un vote
	 *
	 * @param  integer $id
	 * @return mixed
	 */
	public function checkPoll($id)
	{
		return $this->getById($id)->users()->count() != 0;
	}

	/**
	 * Mise à jour sondage suite à modification
	 *
	 * @param  App\Http\Requests\PollUpdateRequest $request;
	 * @param  integer $id
	 * @return void
	 */
	public function update($request, $id)
	{
		// Mise à jour du sondage
		$poll = $this->getById($id);
		$poll->question = $request->input('question');
		$poll->save();

		// Mise à jour des questions
		$poll->answers()->delete();
		$this->saveAnswers($request, $poll);
	}

	/**
	 * Suppression d'un sondage
	 *
	 * @param  integer $id
	 * @return void
	 */
	public function destroy($id)
	{
		$poll = $this->getById($id);
		// Suppression des réponses
		$poll->answers()->delete();
		// Détachement des utilisateurs
		$poll->users()->detach();
		// Suppression du sondage
		$poll->delete();
	}

	/**
	 * Récupération sondage par son id
	 *
	 * @param  integer $id
	 * @return void
	 */
	public function getById($id)
	{
		return Poll::find($id);
	}

	/**
	 * Mise à jour d'un vote
	 *
	 * @param  integer $id
	 * @param  integer $option_id
	 * @param  App\Models\User $user
	 * @return void
	 */
	public function saveVote($id, $option_id, $user)
	{
		// Mise à jour du résultat pour la réponse
		Answer::whereId($option_id)->increment('result');

		// Mise à jour du vote pour l'utilisateur
		$user->polls()->attach($id);
	}

}