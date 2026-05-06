
# Nuri Backend Web

**Built with [Laravel](https://laravel.com/) + [FrankenPHP](https://frankenphp.dev/) + Docker**  

This is the backend API for Nuri, leveraging Laravel’s robust framework, FrankenPHP for ultra-fast PHP runtime, and Docker for consistent, isolated development environments.

---

## Features

### 1. Stunting Risk Prediction
- Early screening for prenatal stunting risk.
- Integrated with FastAPI ML Service.
- Explainable AI (SHAP/LIME) support to identify key risk factors.
- Humanized input mapping for mobile frontend.

### 2. AI-Powered Nutrition Recommendation
- Rule-based food recommendations mapped from SHAP risk factors.
- Integration with Google Gemini AI for educational support (Cooking Guides, Meal Plans).
- High-performance caching for Gemini responses.
- Decoupled architecture for optimal mobile UX.

---

## Quickstart Guide

1. **Environment Setup**:
   Copy `.env.example` to `.env` and configure:
   ```env
   # ML Service (FastAPI)
   URL_ML_STUNTING=http://your-ml-service:8000
   
   # Gemini AI
   GEMINI_API_KEY=your-gemini-api-key
   GEMINI_MODEL=gemini-2.0-flash
   ```

2. **Docker Deployment**:
   ```bash
   docker compose up -d --build
   ```

3. **Database Initialization**:
   ```bash
   docker exec -it nuri-backend-app php artisan migrate --seed
   # Specifically for Food Recommendation Module:
   docker exec -it nuri-backend-app php artisan db:seed --class=FoodSeeder
   ```

4. **Testing**:
   ```bash
   docker exec -it nuri-backend-app ./vendor/bin/phpunit
   ```

---

## API Documentation

The project includes structured endpoints for:
- `POST /api/stunting/predict` - Risk screening
- `GET /api/stunting/recommendations/{id}` - AI-powered nutrition support
- `GET /api/stunting/history` - Prediction history
- `GET /api/stunting/foods` - Nutrition database search

See `walkthrough.md` for detailed technical architecture and flow diagrams.
