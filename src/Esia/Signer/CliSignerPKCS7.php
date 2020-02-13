<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;

class CliSignerPKCS7 extends AbstractSignerPKCS7 implements SignerInterface
{
    use LoggerAwareTrait;

    /**
     * @param string $message
     * @return string
     * @throws SignFailException
     */

    public function sign($message)
    {
        $this->checkFilesExists();

        // random unique directories for sign
        $messageFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        $signFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        file_put_contents($messageFile, $message);

        $this->run(
            'openssl smime -sign -binary -outform DER -noattr ' .
            '-signer ' . escapeshellarg($this->certPath) . ' ' .
            '-inkey ' . escapeshellarg($this->privateKeyPath) . ' ' .
            '-passin ' . escapeshellarg('pass:' . $this->privateKeyPassword) . ' ' .
            '-in ' . escapeshellarg($messageFile) . ' ' .
            '-out ' . escapeshellarg($signFile)
        );

        $signed = file_get_contents($signFile);
        if ($signed === false) {
            $message = sprintf('cannot read %s file', $signFile);
            $this->logger->error($message);
            throw new SignFailException($message);
        }
        $sign = $this->urlSafe(base64_encode($signed));

        unlink($signFile);
        unlink($messageFile);
        return $sign;
    }

    /**
     * @param $command
     * @return void
     * @throws SignFailException
     */
    private function run($command)
    {
        $process = proc_open(
            $command,
            [
                ['pipe', 'w'], // stdout
                ['pipe', 'w'], // stderr
            ],
            $pipes
        );

        $result = stream_get_contents($pipes[0]);
        fclose($pipes[0]);

        $errors = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $code = proc_close($process);
        if (0 !== $code || $result === false) {
            $errors = $errors ?: 'unknown';
            $this->logger->error('Sign fail');
            $this->logger->error('SSL error: ' . $errors);
            throw new SignFailException($errors);
        }
    }
}
