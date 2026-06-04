// pages/Dashboard.js
import React from 'react';
import { useQuery } from 'react-query';
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
} from '@mui/material';
import {
  TrendingUp,
  TrendingDown,
  Warning,
  CheckCircle,
} from '@mui/icons-material';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { api } from '../services/api';

const StatCard = ({ title, value, icon, color, trend }) => (
  <Card>
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
              {trend > 0 ? <TrendingUp fontSize="small" color="success" /> : <TrendingDown fontSize="small" color="error" />}
              <Typography variant="body2" color={trend > 0 ? 'success.main' : 'error.main'}>
                {Math.abs(trend)}% from last month
              </Typography>
            </Box>
          )}
        </Box>
        <Box sx={{ color: color }}>
          {icon}
        </Box>
      </Box>
    </CardContent>
  </Card>
);

export default function Dashboard() {
  const { data: dashboardData, isLoading } = useQuery('dashboard', () => api.getDashboardData());

  if (isLoading) return <LinearProgress />;

  const stats = [
    { title: 'Total Stock Items', value: dashboardData?.totalStock || 1254, icon: <Inventory />, color: '#1976d2' },
    { title: 'Low Stock Items', value: dashboardData?.lowStock || 24, icon: <Warning />, color: '#ff9800', trend: 12 },
    { title: 'Critical Items', value: dashboardData?.criticalStock || 8, icon: <Warning />, color: '#f44336', trend: 25 },
    { title: 'Open Procurement', value: dashboardData?.openProcurement || 16, icon: <ShoppingCart />, color: '#4caf50', trend: -5 },
    { title: 'Procurement Spend', value: `$${dashboardData?.spend?.toLocaleString() || 45200}`, icon: <AttachMoney />, color: '#9c27b0', trend: 18 },
    { title: 'Pending Quotes', value: dashboardData?.pendingQuotes || 12, icon: <CompareArrows />, color: '#2196f3' },
    { title: 'Suppliers', value: dashboardData?.suppliers || 45, icon: <People />, color: '#009688' },
  ];

  const recentProcurements = [
    { id: 'PR-1001', item: 'Router Board', quantity: 50, status: 'pending', date: '2024-01-15' },
    { id: 'PR-1002', item: 'Fiber Module', quantity: 30, status: 'sourcing', date: '2024-01-14' },
    { id: 'PR-1003', item: 'UPS Battery', quantity: 10, status: 'approved', date: '2024-01-13' },
  ];

  const weeklySpending = [
    { week: 'Week 1', spend: 12500, quotes: 8 },
    { week: 'Week 2', spend: 14200, quotes: 12 },
    { week: 'Week 3', spend: 10800, quotes: 7 },
    { week: 'Week 4', spend: 16700, quotes: 15 },
  ];

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Dashboard
      </Typography>
      <Typography variant="body1" color="textSecondary" paragraph>
        Welcome back! Here's what's happening with your procurement today.
      </Typography>

      <Grid container spacing={3}>
        {stats.map((stat, index) => (
          <Grid item xs={12} sm={6} md={3} key={index}>
            <StatCard {...stat} />
          </Grid>
        ))}
      </Grid>

      <Grid container spacing={3} sx={{ mt: 2 }}>
        <Grid item xs={12} md={8}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Weekly Procurement Activity
            </Typography>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={weeklySpending}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="week" />
                <YAxis yAxisId="left" />
                <YAxis yAxisId="right" orientation="right" />
                <Tooltip />
                <Legend />
                <Bar yAxisId="left" dataKey="spend" fill="#8884d8" name="Spend ($)" />
                <Bar yAxisId="right" dataKey="quotes" fill="#82ca9d" name="Quotes Requested" />
              </BarChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        <Grid item xs={12} md={4}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Low Stock Alerts
            </Typography>
            <List>
              {dashboardData?.lowStockItems?.slice(0, 5).map((item, index) => (
                <ListItem key={index}>
                  <ListItemText
                    primary={item.name}
                    secondary={`Current Stock: ${item.quantity} | Reorder at: ${item.reorderPoint}`}
                  />
                  <Chip
                    label={item.quantity === 0 ? "Critical" : "Low"}
                    color={item.quantity === 0 ? "error" : "warning"}
                    size="small"
                  />
                </ListItem>
              ))}
            </List>
          </Paper>
        </Grid>

        <Grid item xs={12}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Recent Procurement Requests
            </Typography>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Request ID</TableCell>
                    <TableCell>Item</TableCell>
                    <TableCell align="right">Quantity</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Date</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {recentProcurements.map((request) => (
                    <TableRow key={request.id}>
                      <TableCell>{request.id}</TableCell>
                      <TableCell>{request.item}</TableCell>
                      <TableCell align="right">{request.quantity}</TableCell>
                      <TableCell>
                        <Chip
                          label={request.status}
                          color={
                            request.status === 'approved' ? 'success' :
                            request.status === 'pending' ? 'warning' : 'info'
                          }
                          size="small"
                        />
                      </TableCell>
                      <TableCell>{request.date}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Paper>
        </Grid>
      </Grid>
    </Box>
  );
}