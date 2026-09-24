<?php

declare(strict_types=1);

namespace Tests\Security;

use FrontendComments\FrontendCommentArray;
use ProcessWire\Config;
use ProcessWire\Database;
use ProcessWire\Page;
use ProcessWire\TestServices;
use ProcessWire\WireInput;
use Tests\Support\TestCase;

/**
 * Regression test for the reflected-XSS fix in FrontendCommentArray::saveVotes() (FrontendCommentArray.php).
 *
 * The Ajax vote handler used to echo the raw "vote" query-string value straight into the
 * data-votetype HTML attribute of its response (`data-votetype="' . $vote . '"`), with no
 * escaping and no whitelist - a crafted `vote` parameter (e.g. `up"><script>...`) breaks out of
 * the attribute. The fix derives a whitelisted 'up'/'down' string from $value (already reduced to
 * exactly 1 or -1) instead of echoing $vote itself.
 */
final class FrontendCommentArrayVoteEchoTest extends TestCase
{
    private function newArrayReadyToVote(int $existingUpvotes = 3): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);

        $field = $this->newField(10, 'comments');
        $field->set('input_fc_vote', 1);
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $this->setProp($array, 'page', $page);

        $this->setProp($array, 'userdata', [
            'user_id' => 5,
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $comment = $this->newWithoutConstructor(\FrontendComments\FrontendComment::class);
        $comment->set('id', 42);
        $comment->set('upvotes', $existingUpvotes);
        $comment->set('downvotes', 1);
        $array->findResult = [$comment];

        /** @var Config $config */
        $config = TestServices::get('config');
        $config->ajax = true;

        /** @var Database $db */
        $db = TestServices::get('database');
        $db->queueResult(rowCount: 0);   // 1) "already voted?" check -> not yet voted
        $db->queueResult(rowCount: 1);   // 2) INSERT into votes table -> success
        $db->queueResult(rowCount: 1);   // 3) UPDATE field table -> success

        return $array;
    }

    public function testMaliciousVoteParameterCannotBreakOutOfTheAttribute(): void
    {
        $array = $this->newArrayReadyToVote();

        $payload = 'up" onmouseover="alert(document.cookie)';
        /** @var WireInput $input */
        $input = TestServices::get('input');
        $input->setQueryString('votecommentid=42&vote=' . rawurlencode($payload));

        $output = $this->captureOutput(fn() => $this->callMethod($array, 'saveVotes'));

        self::assertStringNotContainsString('onmouseover', $output, 'the raw vote payload must never reach the response markup');
        self::assertStringNotContainsString($payload, $output);
        // anything that isn't literally "up" collapses to the "down" branch of the whitelist -
        // the important guarantee is that it is ALWAYS one of these two literal values.
        self::assertMatchesRegularExpression('/data-votetype="(up|down)"/', $output);
    }

    public function testLegitimateUpVoteStillRendersCorrectly(): void
    {
        $array = $this->newArrayReadyToVote(existingUpvotes: 7);

        /** @var WireInput $input */
        $input = TestServices::get('input');
        $input->setQueryString('votecommentid=42&vote=up');

        $output = $this->captureOutput(fn() => $this->callMethod($array, 'saveVotes'));

        self::assertStringContainsString('data-votetype="up"', $output);
        self::assertStringContainsString('>8<', $output, 'upvotes should have been incremented by one');
    }
}
