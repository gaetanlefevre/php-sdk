<?php

namespace Emonsite\Emstorage\PhpSdk\Client;

use App\App;
use Emonsite\Emstorage\PhpSdk\Exception\ResponseException;
use Emonsite\Emstorage\PhpSdk\Model\Collection;
use Emonsite\Emstorage\PhpSdk\Model\EmObject;
use Emonsite\Emstorage\PhpSdk\Model\ObjectSummaryInterface;
use Emonsite\Emstorage\PhpSdk\Normalizer\CollectionNormalizer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\StreamInterface;
use Silex\Application;
use Symfony\Component\Serializer\Serializer;

/**
 * TODO exception spécifique ?
 */
class ObjectClient extends AbstractClient
{
    public function __construct(Client $client, Serializer $serializer, $containerKey)
    {
        parent::__construct($client, $serializer);
        $this->containerKey = $containerKey;
    }
    // TODO $container ?

    // TODO inject container
    /**
     * Créer un objet vide sur le cloud
     * @param ObjectSummaryInterface $object
     * @return ObjectSummaryInterface
     * @throws ResponseException
     */
    public function createFromObject(ObjectSummaryInterface $object)
    {
        // créer le fichier
        try {
            $response = $this->client->post('/objects/'.$this->containerKey, [
                'json' => $this->serializer->normalize($object),
            ]);
        } catch (RequestException $e) {
            throw new ResponseException($e);
        }

        return $this->serializer->deserialize($response->getBody(), EmObject::class, 'json');
    }

    /**
     * Remplie un objet existant dans le cloud
     * @param ObjectSummaryInterface $objectSummary
     * @param string | resource | StreamInterface $content
     * @return ObjectSummaryInterface
     * @throws ResponseException
     */
    public function writeInObject(ObjectSummaryInterface $objectSummary, $content)
    {
        try {
            $response = $this->client->post('/objects/'.$this->containerKey.'/'.$objectSummary->getId().'/bytes', [
                'body' => $content,
                'headers' => [
                    'Content-Type' => 'application/octet-stream',
                ]
            ]);
        } catch (RequestException $e) {
            throw new ResponseException($e);
        }
        return $this->serializer->deserialize($response->getBody(), EmObject::class, 'json');
    }

    /**
     * Créer le ficher avec son contenu dans le cloud
     * @param string $path
     * @param string | resource | StreamInterface $content
     * @return ObjectSummaryInterface
     */
    public function create($path, $content)
    {
        $object = new EmObject();
        $object->setFilename($path);
        if (isset($_SESSION['mime'])) {
            $object->setMime($_SESSION['mime']);
            unset($_SESSION['mime']);
        }
        $objectSummary = $this->createFromObject($object);
        return $this->writeInObject($objectSummary, $content);
    }

    /**
     * Update le contenu d'un fichier qui existe déjà
     * @param string $path
     * @param string | resource | StreamInterface $content
     * @return ObjectSummaryInterface
     */
    public function update($path, $content)
    {
        $this->delete($path);
        return $this->create($path, $content);
    }

    /**
     * @param int $offset
     * @param int $limit
     * @return Collection|ObjectSummaryInterface[]
     * @throws ResponseException
     */
    public function getObjects($offset = 0, $limit = 5)
    {
        try {
            $response = $this->client->get('/objects/'.$this->containerKey, [
                'query' => [
                    'offset' => $offset,
                    'limit' => $limit,
                ],
            ]);
        } catch (RequestException $e) {
            throw new ResponseException($e);
        }

        return $this->serializer->deserialize($response->getBody(), EmObject::class.'[]', 'json', [CollectionNormalizer::ELEMENTS_KEY => 'objects']);
    }

    /**
     * @param $path
     */
    public function delete($path)
    {
        $object = $this->getObject($path);
        $this->deleteFromObject($object);
    }

    /**
     * @param ObjectSummaryInterface $objectSummary
     * @return \Psr\Http\Message\ResponseInterface
     * @throws ResponseException
     */
    public function deleteFromObject(ObjectSummaryInterface $objectSummary)
    {
        try {
            $this->client->delete('/objects/'.$this->containerKey.'/'.$objectSummary->getId());
        } catch (RequestException $e) {
            throw new ResponseException($e);
        }
    }

    /**
     * @param $id
     * @return ObjectSummaryInterface
     * @throws ResponseException
     */
    public function getObjectById($id)
    {
        try {
            $response = $this->client->get('/objects/'.$this->containerKey.'/'.$id);
        } catch (RequestException $e) {
            throw new ResponseException($e);
        }

        return $this->serializer->deserialize($response->getBody(), EmObject::class, 'json');
    }

    /**
     * Permet de récupérer les objets, renvoi true s'il y en a
     * @param string $path
     * @return bool
     * @throws ResponseException
     */
    public function hasObject($path)
    {
        try {
            $response = $this->client->get('/objects/'.$this->containerKey, [
                'query' => [
                    'filename' => $path
                ]
            ]);

            $response = \GuzzleHttp\json_decode($response->getBody()->getContents());
            return (bool)$response->objects;
        } catch (RequestException $e) {
            throw new ResponseException($e);
        }
    }

    /**
     * @param string $path
     * @return ObjectSummaryInterface
     * @throws ResponseException
     */
    public function getObject($path)
    {
        try {
            $response = $this->client->get('/objects/'.$this->containerKey, [
                'query' => [
                    'filename' => $path
                ]
            ]);

        } catch (RequestException $e) {
            throw new ResponseException($e);
        }

        /** @var Collection $objects */
        $objects = $this->serializer->deserialize($response->getBody(), EmObject::class.'[]', 'json', [CollectionNormalizer::ELEMENTS_KEY => 'objects']);

        if (count($objects) != 1) {
            throw new \LogicException(sprintf('Unexpected result, 1 object expected, %s received', count($objects)));
        }

        return $objects[0];
    }
}
