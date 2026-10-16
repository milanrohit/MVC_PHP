<?php
declare(strict_types=1);

namespace App\Backend\Controllers; // 👈 Updated namespace to match folder path


use App\Models\User;
use App\Services\UserService;
use Core\Controller;
use Core\Request;
use Core\Response;
use InvalidArgumentException;

final class HomeController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        return $this->render('backend/home/index', ['title' => 'Home']);
    }

    public function users(Request $request): Response
    {
        return $this->json(array_map(fn (User $u) => $u->toArray(), $this->users->list()));
    }

    public function store(Request $request): Response
    {
        try {
            $user = $this->users->register((string) $request->input('name', ''), (string) $request->input('email', ''));
            return $this->json($user->toArray(), 201);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }
}
