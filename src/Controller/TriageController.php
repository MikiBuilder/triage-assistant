<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ChatAnalysisException;
use App\Service\ChatAnalyzerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TriageController extends AbstractController
{
    public function __construct(
        private readonly ChatAnalyzerService $analyzer,
    ) {
    }

    #[Route('/triage', name: 'triage_view', methods: ['GET'])]
    public function view(): Response
    {
        $results = $this->processChats();

        return $this->render('triage/index.html.twig', [
            'results' => $results,
        ]);
    }

    #[Route('/api/triage', name: 'triage_api', methods: ['GET'])]
    public function api(): JsonResponse
    {
        $results = $this->processChats();

        $output = array_map(function (array $item) {
            if ($item['error']) {
                return [
                    'chat_id'       => $item['chat']['chat_id'],
                    'customer_name' => $item['chat']['customer_name'],
                    'error'         => $item['error'],
                ];
            }

            return [
                'chat_id'       => $item['chat']['chat_id'],
                'customer_name' => $item['chat']['customer_name'],
                'analysis'      => $item['analysis']->toArray(),
            ];
        }, $results);

        return $this->json($output);
    }

    // Loads chats from the JSON file, analyzes each one and sorts by urgency.
    private function processChats(): array
    {
        $chats = $this->loadChats();
        $results = [];

        foreach ($chats as $chat) {
            try {
                $analysis = $this->analyzer->analyze($chat['messages']);
                $results[] = [
                    'chat'     => $chat,
                    'analysis' => $analysis,
                    'error'    => null,
                ];
            } catch (ChatAnalysisException $e) {
                // A failure on one chat does not stop the rest from being processed.
                $results[] = [
                    'chat'     => $chat,
                    'analysis' => null,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        // Sort by urgency descending so agents see critical cases first.
        usort($results, function (array $a, array $b) {
            $urgencyA = $a['analysis']?->urgency ?? 0;
            $urgencyB = $b['analysis']?->urgency ?? 0;

            return $urgencyB <=> $urgencyA;
        });

        return $results;
    }

    private function loadChats(): array
    {
        $path = $this->getParameter('kernel.project_dir') . '/data/mock_chats.json';

        return json_decode(file_get_contents($path), true);
    }
}