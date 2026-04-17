# k6 Performance Tests for Prenava Backend

Performance testing suite for the Prenava Health App backend API.

## Prerequisites

1. Install k6:
   ```bash
   brew install k6  # macOS
   # OR
   choco install k6  # Windows
   # OR
   sudo apt-get install k6  # Linux
   ```

2. Create test credentials:
   ```bash
   cp .env.test.example .env.test
   # Edit .env.test with your test credentials
   ```

3. Create a test user account on the server:
   - Use email: `k6-test@example.com` (or your preferred test email)
   - Password: `TestPassword123!` (or your secure password)
   - Update `.env.test` with these credentials

## Test Scenarios

| Scenario | File | Purpose | Load |
|----------|------|---------|------|
| Auth Test | `01-auth-test.js` | Login/Register performance | 100-500 users |
| Community Test | `02-community-test.js` | Social features, caching | 200-1000 users |
| ML Prediction | `03-ml-prediction-test.js` | ML endpoints response time | 50-200 users |
| Data Heavy | `04-data-heavy-test.js` | Large data endpoints | 100-500 users |
| Mixed Workload | `05-mixed-workload-test.js` | Real-world usage simulation | 500-2000 users |
| Stress Test | `06-stress-test.js` | Find system limits | Up to 5000 users |

## Running Tests

### Run individual test:
```bash
cd k6
k6 run scenarios/01-auth-test.js
```

### Run with custom API URL:
```bash
API_URL=https://staging.prenava.cloud/api k6 run scenarios/02-community-test.js
```

### Run with output to file:
```bash
k6 run --out json=results.json scenarios/05-mixed-workload-test.js
```

### Run with HTML report:
```bash
k6 run --out json=results.json scenarios/05-mixed-workload-test.js
# Then use https://k6.io/html or k6-dashboard to visualize
```

## Test Schedule

### Phase 1: Baseline (Day 1-2)
Run all scenarios individually to establish baseline performance:
```bash
k6 run scenarios/01-auth-test.js
k6 run scenarios/02-community-test.js
k6 run scenarios/03-ml-prediction-test.js
k6 run scenarios/04-data-heavy-test.js
```

### Phase 2: Mixed Workload (Day 3)
Run realistic user simulation:
```bash
k6 run scenarios/05-mixed-workload-test.js
```

### Phase 3: Stress Testing (Day 4-5)
Find system limits (run during off-peak hours!):
```bash
k6 run scenarios/06-stress-test.js
```

## Thresholds

| Metric | Target | Critical |
|--------|--------|----------|
| Auth P95 | < 300ms | > 500ms |
| Community P95 | < 400ms | > 800ms |
| ML Prediction P95 | < 2000ms | > 5000ms |
| Data Heavy P95 | < 1000ms | > 2000ms |
| Error Rate | < 1% | > 5% |

## Interpreting Results

### Key Metrics:
- **P50/P95/P99**: Response time percentiles (50%, 95%, 99% of requests)
- **http_reqs**: Requests per second
- **http_req_failed**: Error rate
- **vus**: Active virtual users

### What to Look For:
1. **Response time degradation**: At what user count does P95 spike?
2. **Error rate**: When does error rate exceed 1%?
3. **Bottlenecks**: Which endpoints are slowest?
4. **Recovery**: Does system recover after load reduces?

## ⚠️ Important Warnings

1. **Testing on Production**: These tests hit `https://prenavabe.cloud/api`
   - Run during off-peak hours (outside 9 AM - 9 PM WIB)
   - Monitor server resources during testing
   - Have a rollback plan ready

2. **Data Safety**:
   - Use dedicated test accounts only
   - Test data should be clearly identifiable (k6-test, etc.)
   - Clean up test data after testing

3. **Server Monitoring**:
   - Monitor CPU, memory, disk I/O during tests
   - Check Laravel logs for errors
   - Monitor database connections

## Troubleshooting

### "Login failed" errors:
- Verify test user exists on the server
- Check credentials in `.env.test`
- Ensure API URL is correct

### High error rates:
- Check if rate limiting is blocking requests
- Verify server has sufficient resources
- Check Laravel logs: `tail -f storage/logs/laravel.log`

### Slow response times:
- Check database query performance
- Verify Redis caching is working
- Check if queue workers are running

## Next Steps After Testing

1. Analyze results and identify bottlenecks
2. Implement optimizations (caching, query optimization, etc.)
3. Re-run tests to verify improvements
4. Document baseline metrics for future comparison
