<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\Debug;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Connection extends AbstractConnectionMiddleware
{
    public function __construct(
        ConnectionInterface $connection,
        private readonly DebugDataHolder $debugDataHolder,
        private readonly ?Stopwatch $stopwatch,
        private readonly string $connectionName,
        private readonly ?ConnectionRegistry $connectionRegistry = null,
        private readonly ?\Closure $disabled = null,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new Statement(
            parent::prepare($sql),
            $this->debugDataHolder,
            $this->connectionName,
            $sql,
            $this->stopwatch,
            $this->connectionRegistry?->getConnection($this->connectionName),
            $this->disabled,
        );
    }

    public function query(string $sql): Result
    {
        if ($this->disabled?->__invoke()) {
            return parent::query($sql);
        }

        $query = new Query($sql, $this->connectionRegistry?->getConnection($this->connectionName));
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            return parent::query($sql);
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }

    public function exec(string $sql): int
    {
        if ($this->disabled?->__invoke()) {
            return parent::exec($sql);
        }

        $query = new Query($sql, $this->connectionRegistry?->getConnection($this->connectionName));
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            $affectedRows = parent::exec($sql);
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }

        return $affectedRows;
    }

    public function beginTransaction(): void
    {
        if ($this->disabled?->__invoke()) {
            parent::beginTransaction();

            return;
        }

        $query = new Query('"START TRANSACTION"', $this->connectionRegistry?->getConnection($this->connectionName));
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            parent::beginTransaction();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }

    public function commit(): void
    {
        if ($this->disabled?->__invoke()) {
            parent::commit();

            return;
        }

        $query = new Query('"COMMIT"', $this->connectionRegistry?->getConnection($this->connectionName));
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            parent::commit();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }

    public function rollBack(): void
    {
        if ($this->disabled?->__invoke()) {
            parent::rollBack();

            return;
        }

        $query = new Query('"ROLLBACK"', $this->connectionRegistry?->getConnection($this->connectionName));
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            parent::rollBack();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
}
