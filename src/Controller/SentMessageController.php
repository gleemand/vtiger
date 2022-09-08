<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SentMessageController extends AbstractController
{
    /**
     * @Route("/", name="app_sent_message")
     */
    public function index(Request $request): Response
    {
        $data = $request->request->get('message');
        $data = $request->query->get('message');

        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/SentMessageController.php',
            'request' => $data,
        ]);
    }
}
