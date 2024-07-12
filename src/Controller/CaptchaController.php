<?php

namespace App\Controller;

use App\Repository\KeyRepository;
use App\Repository\PuzzleRepository;
use App\Service\Puzzle\PuzzleGenerator;
use App\Service\Puzzle\PuzzleImageGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CaptchaController extends AbstractController
{
    #[Route('/captcha/api', name: 'app_captcha_api')]
    public function index(Request $request, 
        PuzzleImageGenerator $imageGenerator,
        KeyRepository $keyRepository): Response
    {

        $params = $request->query->all();
        $challenge = $params['key'] ?? null;

        if (!$challenge) {
            return new Exception('No challenge provided.');
        }

        $key = $keyRepository->findOneBy(['uid' => $challenge]);

        if (!$key) 
        {
            return new Exception('No challenge found.');
        }

        return $imageGenerator->generateImage($key);
    }

    #[Route('/captcha/generatePuzzle', name:'app_captcha_api_generate_key')]
    public function generatePuzzle(PuzzleGenerator $puzzleGenerator, Request $request): JsonResponse  {
        $params = $request->query->all();
        $puzzle = $puzzleGenerator->generatePuzzle($params);

        return $puzzle;
    }
   
    #[Route('/captcha/getPuzzle', name:'app_captcha_api_get_puzzle')]
    public function getPuzzle(Request $request, 
        KeyRepository $keyRepository,
        PuzzleRepository $puzzleRepository,
        PuzzleGenerator $puzzleGenerator): JsonResponse {
        $uid = $request->query->get('key');
        $key = $keyRepository->findOneBy(['uid' => $uid]);
        $puzzle = $puzzleRepository->findOneBy(['key' => $key]);

        if (!$puzzle) {
            return new Exception('No puzzle found.');
        }

        return $puzzleGenerator->getParams($puzzle);
    }

    #[Route('/captcha/verify', name:'app_captcha_api_verify')]
    public function verify(Request $request, PuzzleGenerator $puzzleGenerator): JsonResponse
    {
        $params = $request->query->all();

        return $puzzleGenerator->verify($params['key'], $params['answers']);
    }

    #[Route('/captcha/connect', name:'app_captcha_api_connect')]
    public function connection(Request $request, PuzzleGenerator $puzzleGenerator) {

        $user_key = $request->query->get('user_key');

        $autorization = $puzzleGenerator->connect($user_key);

        if (!$autorization) return new JsonResponse(['valid' => false]);
        
        return new JsonResponse(['valid' => true]);
    }
}
