<?php

namespace App\Service\Puzzle;

use App\Entity\Key;
use App\Entity\Puzzle;
use App\Service\CaptchaImageGeneratorInterface;
use Exception;
use Intervention\Image\ImageManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class PuzzleImageGenerator implements CaptchaImageGeneratorInterface
{
    private $appKernel;

    public function __construct(private readonly PuzzleGenerator $puzzle, KernelInterface $appKernel) {
        $this->appKernel = $appKernel;
    }

    public function chosingAPic(): string {

        $imagesFormat = ['jpg', 'jpeg', 'png', 'webp'];
        
        $images = [];
                
        $project_dir = $this->appKernel->getProjectDir();
        dump($project_dir);
        $directory = $project_dir.'/public/images';

        foreach($imagesFormat as $format) {
            $pattern = $directory . '/*.' . $format;
            $images = array_merge($images, glob($pattern));
        }

        if (empty($images)) {
            return new Exception('No images found in the directory.');
        }

        $randomImage = $images[array_rand($images)];

        return $randomImage;
    }

    public function getPieces(int $number): array 
    {
        $pieces = [];
        $project_dir = $this->appKernel->getProjectDir();

        $directory = $project_dir.'/assets/images/pieces';

        $finder = new Finder();
        $finder->files()->in($directory)->notName('*_halo*');

        if ($finder->hasResults()) {
            foreach($finder as $file) {
                if (count($pieces) == $number) break; 
                $pieces[] = $file->getRealPath();
            }
        }

        return $pieces;
    }

    private function resizeNecessary($piece, string $value = 'width', Puzzle $puzzle) {
        $size = 0;
        $positions = [];
        $width = 0;
        $height = 0;
        if ($value == 'width') {
            $size = $piece->width();
            $width = intval(($puzzle->getPieceWidth() - $size) / 2);
            $positions = ['left', 'right'];
        } elseif ($value == 'height') {
            $size = $piece->height();
            $height = intval(($puzzle->getPieceHeight() - $size) / 2);
            $positions = ['top', 'bottom'];
        }

        foreach ($positions as $position) {
            $piece->resizeCanvas(
                $width,
                $height,
                $position, 
                true, 
                'rgba(0, 0, 0, 0)'
            );
        }

        return $piece;
    } 

    public function generateImage(Key $key): Response {
        $positions = $key->getPositions();
        $puzzle = $key->getPuzzle();

        if ($positions->isEmpty()) {
            return new Response('No position found', 404);
        }

        $backgroundPath = $this->chosingAPic();
        
        // Make the image
        $manager = new ImageManager(['driver' => 'gd']);
        $image = $manager->make($backgroundPath);
        $image->resize($puzzle->getWidth(), $puzzle->getHeight());
        $pieces = $this->getPieces($puzzle->getPiecesNumber());

        $holes = [];


        foreach ($pieces as $index => $piece) {
            $piece = $manager->make($piece);
            $piece->resize($puzzle->getPieceWidth(), $puzzle->getPieceHeight(), function ($constraint) {
                $constraint->aspectRatio();
            });
            if ($piece->height() < $puzzle->getHeight()) {
                $piece = $this->resizeNecessary($piece, 'height', $puzzle);
            } else if ($piece->width() < $puzzle->getWidth()) {
                $piece = $this->resizeNecessary($piece, 'width', $puzzle);
            }
            
            $hole = clone $piece;
            $hole->opacity(80);

            $position = $positions[$index];

            // create the piece with the image in it
            $piece->insert($image, 'top-left', -$position->getX(), -$position->getY())
                // and then crop it to the piece size
                  ->mask($hole, true);

            $holes[] = $hole;
            $pieces[$index] = $piece;
        }

        $image
            ->resizeCanvas(
                $puzzle->getPieceWidth(),
                0,
                'left',
                true,
                'rgba(0, 0, 0, 0)'
            )
            ->resizeCanvas(
                $puzzle->getPieceHeight(),
                0,
                'right',
                true,
                'rgba(0, 0, 0, 0)'
            );

        // Generate the positions for the pieces
        // Easy way
        $piecesPositionsInImages = ['top-left', 'top-right', 'bottom-right', ];

        foreach($pieces as $index => $piece) {
            $position = $positions[$index];
            $hole = $holes[$index];

            $randomPiecePosition = $piecesPositionsInImages[$index];

            $image
                ->insert($piece, $randomPiecePosition)
                ->insert($hole->opacity(80), 'top-left', $position->getX() + $puzzle->getPieceWidth(), $position->getY());
        }                
        
        return $image->response('webp');
    }
}