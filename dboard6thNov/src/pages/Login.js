// pages/Login.js
import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import {
  Box,
  Container,
  Paper,
  Typography,
  TextField,
  Button,
  InputAdornment,
  IconButton,
  Alert,
  CircularProgress,
  Divider,
  Chip,
  Avatar,
  Fade,
  Slide,
  useTheme,
  FormControlLabel,
  Checkbox,
} from '@mui/material';
import {
  Email,
  Lock,
  Visibility,
  VisibilityOff,
  Storefront,
  Login as LoginIcon,
  AdminPanelSettings,
  ShoppingCart,
  Inventory,
  AttachMoney,
  People,
} from '@mui/icons-material';
import { motion } from 'framer-motion';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);
  const [selectedRole, setSelectedRole] = useState('all');
  
  const navigate = useNavigate();
  const { login } = useAuth();
  const theme = useTheme();

  const roleOptions = [
    { value: 'all', label: 'All Departments', icon: <People />, color: '#1976d2' },
    { value: 'procurement', label: 'Procurement', icon: <ShoppingCart />, color: '#2e7d32' },
    { value: 'sales', label: 'Sales', icon: <Storefront />, color: '#ed6c02' },
    { value: 'inventory', label: 'Inventory', icon: <Inventory />, color: '#9c27b0' },
    { value: 'finance', label: 'Finance', icon: <AttachMoney />, color: '#0288d1' },
    { value: 'admin', label: 'Administration', icon: <AdminPanelSettings />, color: '#d32f2f' },
  ];

  useEffect(() => {
    // Check for saved credentials
    const savedEmail = localStorage.getItem('remembered_email');
    if (savedEmail) {
      setEmail(savedEmail);
      setRememberMe(true);
    }
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsLoading(true);
    setError(null);

    try {
      const response = await login({ 
        email, 
        password, 
        rememberMe,
        department: selectedRole !== 'all' ? selectedRole : undefined 
      });
      
      // Store user preferences
      if (rememberMe) {
        localStorage.setItem('remembered_email', email);
      } else {
        localStorage.removeItem('remembered_email');
      }

      // Navigate to user's dashboard
      const dashboardUrl = response.user.dashboard_url || '/dashboard';
      navigate(dashboardUrl);
    } catch (err) {
      setError(err.message || 'Login failed. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.secondary.main} 100%)`,
        padding: 2,
      }}
    >
      <Container maxWidth="sm">
        <Fade in timeout={1000}>
          <Paper
            elevation={24}
            sx={{
              p: 4,
              borderRadius: 4,
              backdropFilter: 'blur(20px)',
              bgcolor: 'rgba(255, 255, 255, 0.95)',
            }}
          >
            {/* Logo and Header */}
            <Box textAlign="center" mb={4}>
              <motion.div
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ duration: 0.5 }}
              >
                <Avatar
                  sx={{
                    width: 80,
                    height: 80,
                    mx: 'auto',
                    mb: 2,
                    bgcolor: theme.palette.primary.main,
                  }}
                >
                  <Storefront sx={{ fontSize: 40 }} />
                </Avatar>
              </motion.div>
              <Typography variant="h4" fontWeight="bold" color="primary">
                Micronet Procurement
              </Typography>
              <Typography variant="body2" color="text.secondary">
                Enterprise Procurement Automation Platform
              </Typography>
              <Divider sx={{ my: 2 }} />
              <Typography variant="body2" color="text.secondary">
                Sign in to your account
              </Typography>
            </Box>

            {/* Error Alert */}
            {error && (
              <Slide direction="down" in mountOnEnter unmountOnExit>
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
                  {error}
                </Alert>
              </Slide>
            )}

            {/* Role Selection */}
            <Box mb={3}>
              <Typography variant="body2" color="text.secondary" gutterBottom>
                Select Department
              </Typography>
              <Box display="flex" flexWrap="wrap" gap={1}>
                {roleOptions.map((role) => (
                  <Chip
                    key={role.value}
                    icon={role.icon}
                    label={role.label}
                    onClick={() => setSelectedRole(role.value)}
                    color={selectedRole === role.value ? 'primary' : 'default'}
                    variant={selectedRole === role.value ? 'filled' : 'outlined'}
                    sx={{
                      '&:hover': {
                        bgcolor: selectedRole === role.value ? 'primary.main' : 'action.hover',
                      },
                    }}
                  />
                ))}
              </Box>
            </Box>

            {/* Login Form */}
            <form onSubmit={handleSubmit}>
              <TextField
                fullWidth
                label="Email Address"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                margin="normal"
                required
                autoFocus
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      <Email color="action" />
                    </InputAdornment>
                  ),
                }}
              />

              <TextField
                fullWidth
                label="Password"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                margin="normal"
                required
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      <Lock color="action" />
                    </InputAdornment>
                  ),
                  endAdornment: (
                    <InputAdornment position="end">
                      <IconButton onClick={() => setShowPassword(!showPassword)} edge="end">
                        {showPassword ? <VisibilityOff /> : <Visibility />}
                      </IconButton>
                    </InputAdornment>
                  ),
                }}
              />

              <Box display="flex" alignItems="center" justifyContent="space-between" mt={2}>
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={rememberMe}
                      onChange={(e) => setRememberMe(e.target.checked)}
                      color="primary"
                    />
                  }
                  label="Remember me"
                />
              <Button
  color="primary"
  size="small"
  onClick={() => {
    alert("Login button clicked");
    navigate('/forgot-password');
  }}
>
  Forgot password?
</Button>
              </Box>

              <Button
                type="submit"
                fullWidth
                variant="contained"
                size="large"
                disabled={isLoading}
                sx={{ mt: 3, mb: 2, py: 1.5 }}
                startIcon={!isLoading && <LoginIcon />}
              >
                {isLoading ? (
                  <CircularProgress size={24} color="inherit" />
                ) : (
                  'Sign In'
                )}
              </Button>
            </form>

            {/* Footer */}
            <Box textAlign="center" mt={2}>
              <Typography variant="body2" color="text.secondary">
                New user?{' '}
                <Button
                  color="primary"
                  size="small"
                  onClick={() => navigate('/register')}
                >
                  Create account
                </Button>
              </Typography>
              <Typography variant="caption" color="text.secondary" display="block" mt={1}>
                Version 2.0.0 • © 2026 Micronet Ltd
              </Typography>
            </Box>
          </Paper>
        </Fade>
      </Container>
    </Box>
  );
};

export default Login;
