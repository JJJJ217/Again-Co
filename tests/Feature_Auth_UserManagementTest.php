<?php
use PHPUnit\Framework\TestCase;

/**
 * F101 Registration & Login
 * F102 Profile Management  
 * F103 Reset Password
 * User Story: "As a visitor, I can register, login, and manage my profile securely"
 */
final class Feature_Auth_UserManagementTest extends TestCase
{
    protected function tearDown(): void
    {
        reset_session_flash();
        // Clear any session data
        unset($_SESSION['user_id'], $_SESSION['user_role']);
    }

    public function testPasswordHashingAndVerification(): void
    {
        $password = 'SecurePassword123!';
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $this->assertTrue(password_verify($password, $hashed));
        $this->assertFalse(password_verify('wrongpassword', $hashed));
        $this->assertNotSame($password, $hashed); // Ensure it's actually hashed
    }

    public function testRoleCheckingFunctions(): void
    {
        // Clear session first
        $_SESSION = [];
        
        // Test isAdmin function
        $_SESSION['user_role'] = 'admin';
        $this->assertTrue(isAdmin());
        
        $_SESSION['user_role'] = 'customer';
        $this->assertFalse(isAdmin());
        
        // Test isStaff function
        $_SESSION['user_role'] = 'staff';
        $this->assertTrue(isStaff());
        
        $_SESSION['user_role'] = 'customer';
        $this->assertFalse(isStaff());
        
        // Test with no role set
        unset($_SESSION['user_role']);
        $this->assertFalse(isAdmin());
        $this->assertFalse(isStaff());
    }

    public function testHasRoleFunctionWithArrays(): void
    {
        // Clear session first
        $_SESSION = [];
        
        $_SESSION['user_role'] = 'admin';
        $this->assertTrue(hasRole(['admin', 'staff']));
        $this->assertTrue(hasRole('admin'));
        
        $_SESSION['user_role'] = 'staff';
        $this->assertTrue(hasRole(['admin', 'staff']));
        $this->assertTrue(hasRole('staff'));
        
        $_SESSION['user_role'] = 'customer';
        $this->assertFalse(hasRole(['admin', 'staff']));
        $this->assertTrue(hasRole('customer'));
        $this->assertTrue(hasRole(['customer', 'guest']));
    }

    public function testUserProfileNameSplitting(): void
    {
        // Test the name splitting logic we implemented for checkout
        $fullName = "Michael Sutjiato";
        $parts = explode(' ', $fullName, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';
        
        $this->assertSame('Michael', $firstName);
        $this->assertSame('Sutjiato', $lastName);
        
        // Test single name
        $singleName = "Admin";
        $parts = explode(' ', $singleName, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';
        
        $this->assertSame('Admin', $firstName);
        $this->assertSame('', $lastName);
        
        // Test multiple names
        $multipleName = "John Michael Smith";
        $parts = explode(' ', $multipleName, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';
        
        $this->assertSame('John', $firstName);
        $this->assertSame('Michael Smith', $lastName);
    }

    public function testEmailValidation(): void
    {
        // Test email validation patterns
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'admin@againco.com'
        ];
        
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            ''
        ];
        
        foreach ($validEmails as $email) {
            $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "Email $email should be valid");
        }
        
        foreach ($invalidEmails as $email) {
            $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "Email $email should be invalid");
        }
    }

    public function testSessionManagement(): void
    {
        // Test session data setting and retrieval
        $_SESSION['user_id'] = 123;
        $_SESSION['user_role'] = 'customer';
        $_SESSION['user_name'] = 'Test User';
        
        $this->assertSame(123, $_SESSION['user_id']);
        $this->assertSame('customer', $_SESSION['user_role']);
        $this->assertSame('Test User', $_SESSION['user_name']);
        
        // Test session clearing
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayNotHasKey('user_role', $_SESSION);
        $this->assertArrayNotHasKey('user_name', $_SESSION);
    }
}