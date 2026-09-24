<?php

declare(strict_types=1);

namespace Tests\Security;

use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\TestServices;
use ProcessWire\User;
use Tests\Support\TestCase;

/**
 * Regression test for FieldtypeFrontendComments::noEMailWarning() (FieldtypeFrontendComments.module).
 *
 * This hook used to fire for every logged-in frontend user (isLoggedin()), showing them a
 * module-configuration warning - including a direct link into Setup > Fields for the comments
 * field - even though editing a field's configuration in ProcessWire requires superuser access.
 * Any registered site member could see backend configuration state and an admin edit link that
 * was never actionable (or relevant) to them. The fix restricts the check to isSuperuser().
 */
final class FieldtypeFrontendCommentsPermissionGuardTest extends TestCase
{
    private function fieldMissingNotificationEmail(): \ProcessWire\Field
    {
        $field = $this->newField(10, 'fc_comments');
        $field->set('input_fc_emailtype', 'text');
        $field->set('input_fc_default_to', ''); // no recipient configured -> warning would apply

        /** @var \ProcessWire\Fields $fields */
        $fields = TestServices::get('fields');
        $fields->add($field);

        return $field;
    }

    public function testOrdinaryLoggedInUserNeverSeesTheConfigurationWarning(): void
    {
        $this->fieldMissingNotificationEmail();

        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        /** @var User $user */
        $user = TestServices::get('user');
        $user->setLoggedIn(true)->setSuperuser(false);

        $this->callMethod($fieldtype, 'noEMailWarning');

        self::assertEmpty($fieldtype->notices, 'a plain logged-in frontend user must not see any backend configuration warning');
    }

    public function testSuperuserStillSeesTheConfigurationWarning(): void
    {
        $this->fieldMissingNotificationEmail();

        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        /** @var User $user */
        $user = TestServices::get('user');
        $user->setLoggedIn(true)->setSuperuser(true);

        $this->callMethod($fieldtype, 'noEMailWarning');

        self::assertNotEmpty($fieldtype->notices, 'a superuser must still be warned about the missing notification email so the misconfiguration actually gets fixed');
        self::assertStringContainsString('requires an email address', $fieldtype->notices[0]['text']);
    }

    public function testLoggedOutVisitorNeverSeesTheConfigurationWarning(): void
    {
        $this->fieldMissingNotificationEmail();

        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        /** @var User $user */
        $user = TestServices::get('user');
        $user->setLoggedIn(false)->setSuperuser(false);

        $this->callMethod($fieldtype, 'noEMailWarning');

        self::assertEmpty($fieldtype->notices);
    }
}
