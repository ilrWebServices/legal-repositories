<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class JSONDataTransformer implements DataTransformerInterface
{


    /**
     * Transform an array to a JSON string
     */
    public function transform($array)
    {
        return json_encode($array);
    }

    public function reverseTransform($data): ?string
    {
        if (null === $data) {
            $privateErrorMessage = 'You failed. Sorry bro.';

            $publicErrorMessage = 'The given "{{ data }}" data is not valid json.';

            $failure = new TransformationFailedException($privateErrorMessage);
            $failure->setInvalidMessage($publicErrorMessage, [
                '{{ data }}' => $data,
            ]);

            throw $failure;
        }

        return json_decode($data, true);
    }
}
