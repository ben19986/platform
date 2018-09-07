<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use League\OAuth2\Server\CryptKey;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenAudienceException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;

class JWTFactory implements TokenFactoryInterface
{
    /**
     * @var Key|CryptKey|string
     */
    protected $privateKey;

    /**
     * @param Key|CryptKey|string $privateKey
     */
    public function __construct($privateKey)
    {
        if ($privateKey instanceof CryptKey === false) {
            $privateKey = new CryptKey($privateKey);
        }

        $this->privateKey = $privateKey;
    }

    public function generateToken(OrderTransactionStruct $transaction, Context $context, int $expiresInSeconds = 1800): string
    {
        $jwtToken = (new Builder())
            ->setIssuer($context->getTenantId())
            ->setAudience($context->getTenantId())
            ->setId(Uuid::uuid4()->getHex(), true)
            ->setIssuedAt(time())
            ->setNotBefore(time())
            ->setExpiration(time() + $expiresInSeconds)
            ->setSubject($transaction->getId())
            ->set('pmi', $transaction->getPaymentMethodId())
            ->sign(new Sha256(), new Key($this->privateKey->getKeyPath(), $this->privateKey->getPassPhrase()))
            ->getToken();

        return (string) $jwtToken;
    }

    /**
     * @throws InvalidTokenException
     * @throws InvalidTokenAudienceException
     */
    public function parseToken(string $token, Context $context): TokenStruct
    {
        try {
            $jwtToken = (new Parser())->parse($token);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidTokenException($token, 0, $e);
        }

        if ($jwtToken->verify(new Sha256(), $this->privateKey->getKeyPath()) === false) {
            throw new InvalidTokenException($token);
        }

        if ($jwtToken->getClaim('aud') !== $context->getTenantId()) {
            throw new InvalidTokenAudienceException($jwtToken->getClaim('aud'), $context->getTenantId(), $token);
        }

        $tokenStruct = new TokenStruct(
            $jwtToken->getClaim('jti'),
            $token,
            $jwtToken->getClaim('pmi'),
            $jwtToken->getClaim('sub'),
            $jwtToken->getClaim('exp')
        );

        return $tokenStruct;
    }

    public function invalidateToken(string $token, Context $context): bool
    {
        return false;
    }
}