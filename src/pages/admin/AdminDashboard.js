// pages/admin/AdminDashboard.js
import React, { useState, useEffect } from 'react';
import { useAuth } from '../../hooks/useAuth';
import AdminLayout from '../../components/layouts/AdminLayout';
import {
  Grid,
  Paper,
  Typography,
  Box,
  Card,
  CardContent,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Chip,
  LinearProgress,
  IconButton,
  Tooltip,
  Button,
  Avatar,
  AvatarGroup,
  List,
  ListItem,
  ListItemText,
  ListItemAvatar,
  Divider,
  Tabs,
  Tab,
  Alert,
  Menu,
  MenuItem,
} from '@mui/material';
import {
  TrendingUp,
  TrendingDown,
  Warning,
  CheckCircle,
  People,
  AdminPanelSettings,
  ShoppingCart,
  Inventory,
  AttachMoney,
  Business,
  Settings,
  PersonAdd,
  Refresh,
  Download,
  Print,
  MoreVert,
  Dashboard as DashboardIcon,
  Group,
  Security,
  History,
  Analytics,
} from '@mui/icons-material';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip as RechartsTooltip,
  Legend,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from 'recharts';
import { AdminService } from '../../services/admin.service';

const TabPanel = ({ children, value, index }) => {
  return (
    <div
      role="tabpanel"
      hidden={value !== index}
      id={`admin-tabpanel-${index}`}
      aria-labelledby={`admin-tab-${index}`}
    >
      {value === index && <Box sx={{ p: 3 }}>{children}</Box>}
    </div>
  );
};

const StatCard = ({ title, value, icon, color, trend, subtitle }) => {
  const colors = {
    primary: '#1976d2',
    success: '#2e7d32',
    warning: '#ed6c02',
    error: '#d32f2f',
    info: '#0288d1',
    purple: '#9c27b0',
  };

  return (
    <Card sx={{ height: '100%' }}>
      <CardContent>
        <Box display="flex" justifyContent="space-between" alignItems="center">
          <Box>
            <Typography color="textSecondary" gutterBottom variant="body2">
              {title}
            </Typography>
            <Typography variant="h4" component="h2">
              {value}
            </Typography>
            {trend && (
              <Box display="flex" alignItems="center" mt={1}>
                {trend > 0 ? (
                  <TrendingUp fontSize="small" sx={{ color: 'success.main' }} />
                ) : (
                  <TrendingDown fontSize="small" sx={{ color: 'error.main' }} />
                )}
                <Typography
                  variant="body2"
                  sx={{ color: trend > 0 ? 'success.main' : 'error.main' }}
                >
                  {Math.abs(trend)}% from last month
                </Typography>
              </Box>
            )}
            {subtitle && (
              <Typography variant="body2" color="textSecondary">
                {subtitle}
              </Typography>
            )}
          </Box>
          <Box
            sx={{
              p: 2,
              borderRadius: 2,
              bgcolor: `${colors[color]}15`,
              color: colors[color],
            }}
          >
            {icon}
          </Box>
        </Box>
      </CardContent>
    </Card>
  );
};

const AdminDashboard = () => {
  const { user } = useAuth();
  const [metrics, setMetrics] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [tabValue, setTabValue] = useState(0);
  const [anchorEl, setAnchorEl] = useState(null);
  const [selectedUser, setSelectedUser] = useState(null);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      setIsLoading(true);
      const metricsData = await AdminService.getDashboardMetrics();
      setMetrics(metricsData);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleTabChange = (event, newValue) => {
    setTabValue(newValue);
  };

  const handleMenuOpen = (event, user) => {
    setAnchorEl(event.currentTarget);
    setSelectedUser(user);
  };

  const handleMenuClose = () => {
    setAnchorEl(null);
    setSelectedUser(null);
  };

  if (isLoading) {
    return (
      <AdminLayout>
        <Box sx={{ width: '100%', p: 3 }}>
          <LinearProgress />
          <Typography align="center" sx={{ mt: 2 }}>Loading dashboard...</Typography>
        </Box>
      </AdminLayout>
    );
  }

  const stats = [
    { 
      title: 'Total Users', 
      value: metrics?.totalUsers || 0, 
      icon: <People />, 
      color: 'primary',
      trend: metrics?.userGrowth || 8,
      subtitle: `${metrics?.activeUsers || 0} active`
    },
    { 
      title: 'Departments', 
      value: metrics?.totalDepartments || 7, 
      icon: <Business />, 
      color: 'success',
      trend: 0
    },
    { 
      title: 'Roles', 
      value: metrics?.totalRoles || 14, 
      icon: <AdminPanelSettings />, 
      color: 'purple',
      trend: 0
    },
    { 
      title: 'System Health', 
      value: `${metrics?.systemHealth || 98}%`, 
      icon: <CheckCircle />, 
      color: 'success',
      subtitle: 'All systems operational'
    },
  ];

  // Sample data for charts
  const userActivityData = [
    { day: 'Mon', users: 45, sessions: 67 },
    { day: 'Tue', users: 52, sessions: 89 },
    { day: 'Wed', users: 48, sessions: 75 },
    { day: 'Thu', users: 61, sessions: 94 },
    { day: 'Fri', users: 55, sessions: 82 },
    { day: 'Sat', users: 23, sessions: 34 },
    { day: 'Sun', users: 18, sessions: 28 },
  ];

  const departmentDistribution = [
    { name: 'Procurement', value: 12, color: '#1976d2' },
    { name: 'Sales', value: 15, color: '#2e7d32' },
    { name: 'Inventory', value: 8, color: '#ed6c02' },
    { name: 'Finance', value: 6, color: '#9c27b0' },
    { name: 'IT', value: 5, color: '#0288d1' },
    { name: 'HR', value: 4, color: '#d32f2f' },
    { name: 'Executive', value: 3, color: '#00897b' },
  ];

  const recentUsers = [
    { id: 1, name: 'John Doe', email: 'john@micronet.com', role: 'Procurement Manager', department: 'Procurement', status: 'active' },
    { id: 2, name: 'Jane Smith', email: 'jane@micronet.com', role: 'Sales Director', department: 'Sales', status: 'active' },
    { id: 3, name: 'Bob Johnson', email: 'bob@micronet.com', role: 'Inventory Clerk', department: 'Inventory', status: 'inactive' },
  ];

  return (
    <AdminLayout>
      <Box sx={{ p: 3 }}>
        {/* Header */}
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
          <Box>
            <Typography variant="h4" gutterBottom>
              Admin Dashboard
            </Typography>
            <Typography variant="body1" color="textSecondary">
              Welcome back, {user?.first_name || 'Administrator'}! Here's your system overview.
            </Typography>
          </Box>
          <Box display="flex" gap={1}>
            <Tooltip title="Refresh">
              <IconButton onClick={fetchDashboardData}>
                <Refresh />
              </IconButton>
            </Tooltip>
            <Tooltip title="Export Report">
              <IconButton>
                <Download />
              </IconButton>
            </Tooltip>
            <Tooltip title="Print">
              <IconButton>
                <Print />
              </IconButton>
            </Tooltip>
            <Button variant="contained" startIcon={<PersonAdd />}>
              Add User
            </Button>
          </Box>
        </Box>

        {/* Error Display */}
        {error && (
          <Alert severity="error" sx={{ mb: 3 }} onClose={() => setError(null)}>
            {error}
          </Alert>
        )}

        {/* Stats Cards */}
        <Grid container spacing={3} mb={3}>
          {stats.map((stat, index) => (
            <Grid item xs={12} sm={6} md={3} key={index}>
              <StatCard {...stat} />
            </Grid>
          ))}
        </Grid>

        {/* Tabs */}
        <Paper sx={{ width: '100%', mb: 3 }}>
          <Tabs
            value={tabValue}
            onChange={handleTabChange}
            indicatorColor="primary"
            textColor="primary"
            variant="scrollable"
            scrollButtons="auto"
          >
            <Tab label="Overview" icon={<DashboardIcon />} />
            <Tab label="Users" icon={<People />} />
            <Tab label="Roles & Permissions" icon={<Security />} />
            <Tab label="Audit Logs" icon={<History />} />
            <Tab label="Analytics" icon={<Analytics />} />
          </Tabs>

          <TabPanel value={tabValue} index={0}>
            <Grid container spacing={3}>
              <Grid item xs={12} md={8}>
                <Paper sx={{ p: 2 }}>
                  <Typography variant="h6" gutterBottom>
                    User Activity
                  </Typography>
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={userActivityData}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="day" />
                      <YAxis />
                      <RechartsTooltip />
                      <Legend />
                      <Bar dataKey="users" fill="#1976d2" name="Active Users" />
                      <Bar dataKey="sessions" fill="#2e7d32" name="Sessions" />
                    </BarChart>
                  </ResponsiveContainer>
                </Paper>
              </Grid>
              <Grid item xs={12} md={4}>
                <Paper sx={{ p: 2 }}>
                  <Typography variant="h6" gutterBottom>
                    Department Distribution
                  </Typography>
                  <ResponsiveContainer width="100%" height={300}>
                    <PieChart>
                      <Pie
                        data={departmentDistribution}
                        cx="50%"
                        cy="50%"
                        label
                        dataKey="value"
                      >
                        {departmentDistribution.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                      </Pie>
                      <RechartsTooltip />
                    </PieChart>
                  </ResponsiveContainer>
                </Paper>
              </Grid>
            </Grid>
          </TabPanel>

          <TabPanel value={tabValue} index={1}>
            <Box display="flex" justifyContent="space-between" mb={2}>
              <Typography variant="h6">User Management</Typography>
              <Button variant="contained" startIcon={<PersonAdd />}>
                Add New User
              </Button>
            </Box>
            <TableContainer component={Paper}>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>User</TableCell>
                    <TableCell>Email</TableCell>
                    <TableCell>Role</TableCell>
                    <TableCell>Department</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell align="right">Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {recentUsers.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell>
                        <Box display="flex" alignItems="center" gap={1}>
                          <Avatar sx={{ width: 32, height: 32 }}>
                            {user.name.charAt(0)}
                          </Avatar>
                          <Typography>{user.name}</Typography>
                        </Box>
                      </TableCell>
                      <TableCell>{user.email}</TableCell>
                      <TableCell>{user.role}</TableCell>
                      <TableCell>{user.department}</TableCell>
                      <TableCell>
                        <Chip
                          label={user.status}
                          color={user.status === 'active' ? 'success' : 'default'}
                          size="small"
                        />
                      </TableCell>
                      <TableCell align="right">
                        <IconButton size="small" onClick={(e) => handleMenuOpen(e, user)}>
                          <MoreVert />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
            <Menu
              anchorEl={anchorEl}
              open={Boolean(anchorEl)}
              onClose={handleMenuClose}
            >
              <MenuItem onClick={handleMenuClose}>Edit User</MenuItem>
              <MenuItem onClick={handleMenuClose}>Change Role</MenuItem>
              <MenuItem onClick={handleMenuClose}>Reset Password</MenuItem>
              <MenuItem onClick={handleMenuClose} sx={{ color: 'error.main' }}>
                Disable User
              </MenuItem>
            </Menu>
          </TabPanel>
        </Paper>
      </Box>
    </AdminLayout>
  );
};

export default AdminDashboard;