<?php

return [
    /**
     * Marques mises en avant automatiquement (carrousel de l'accueil, produits phares), dans cet ordre.
     * Un produit en fait partie si son nom correspond au motif (insensible à la casse). Aucun réglage manuel : le catalogue
     * synchronisé depuis le fournisseur suffit. Un rang « phare » défini à la main dans le backoffice reste prioritaire.
     */
    'highlight_brands' => [
        ['label' => 'Netflix', 'pattern' => '/netflix/i'],
        ['label' => 'Spotify', 'pattern' => '/spotify/i'],
        ['label' => 'Prime Video', 'pattern' => '/prime\s*video/i'],
        ['label' => 'Claude', 'pattern' => '/claude/i'],
        ['label' => 'ChatGPT', 'pattern' => '/chat\s*gpt/i'],
        ['label' => 'Canva', 'pattern' => '/canva/i'],
        ['label' => 'CapCut', 'pattern' => '/cap\s*cut/i'],
        ['label' => 'Apple Music', 'pattern' => '/apple\s*music/i'],
        ['label' => 'Apple TV', 'pattern' => '/apple\s*tv/i'],
        ['label' => 'ElevenLabs', 'pattern' => '/eleven\s*labs/i'],
    ],
    /** Nombre maximal de slides dans le carrousel de l'accueil. */
    'carousel_size' => 10,
];
