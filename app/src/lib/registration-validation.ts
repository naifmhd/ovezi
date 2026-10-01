export function registrationValidationMessage(name: string, email: string, password: string, confirmation: string): string | null {
  if (!name.trim()) return 'Enter your name to create your account.';
  if (!email.trim()) return 'Enter your email address.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) return 'Enter a valid email address.';
  if (password.length < 8) return 'Use at least 8 characters for your password.';
  if (!confirmation) return 'Repeat your password in Confirm password.';
  if (password !== confirmation) return 'Your passwords do not match. Check both password fields.';
  return null;
}
