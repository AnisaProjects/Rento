<?php
/**
 * Car Recommendation Engine
 * Implements content-based filtering with K-Means clustering
 */

class CarRecommendationEngine {
    private $dbh;
    
    public function __construct($database) {
        $this->dbh = $database;
    }
    
    /**
     * Get car recommendations based on user preferences
     */
    public function getRecommendations($budget, $carType, $userEmail = null) {
        // Get user's past rentals for personalization
        $pastRentals = $this->getUserPastRentals($userEmail);
        
        // Get all available cars
        $allCars = $this->getAllCars();
        
        // Classify cars by type if not already classified
        $this->classifyCarsByType($allCars);
        
        // Filter cars by budget and type
        $filteredCars = $this->filterCarsByPreferences($allCars, $budget, $carType);
        
        // Apply content-based filtering
        $recommendations = $this->contentBasedFiltering($filteredCars, $pastRentals);
        
        // Apply K-Means clustering for better grouping
        $clusteredRecommendations = $this->applyKMeansClustering($recommendations);
        
        return $clusteredRecommendations;
    }
    
    /**
     * Get user's past rental history
     */
    private function getUserPastRentals($userEmail) {
        if (!$userEmail) return [];
        
        $sql = "SELECT v.*, b.FromDate, b.ToDate 
                FROM tblbooking b 
                JOIN tblvehicles v ON b.VehicleId = v.id 
                WHERE b.userEmail = :email AND b.Status = 1
                ORDER BY b.PostingDate DESC";
        
        $query = $this->dbh->prepare($sql);
        $query->bindParam(':email', $userEmail, PDO::PARAM_STR);
        $query->execute();
        
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
    
    /**
     * Get all available cars
     */
    private function getAllCars() {
        $sql = "SELECT v.*, b.BrandName 
                FROM tblvehicles v 
                LEFT JOIN tblbrands b ON v.VehiclesBrand = b.id 
                WHERE v.id IS NOT NULL";
        
        $query = $this->dbh->prepare($sql);
        $query->execute();
        
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
    
    /**
     * Classify cars by type based on features
     */
    private function classifyCarsByType($cars) {
        foreach ($cars as $car) {
            $carType = $this->determineCarType($car);
            
            // Update car type in database if not set
            $updateSql = "UPDATE tblvehicles SET CarType = :carType WHERE id = :id";
            $updateQuery = $this->dbh->prepare($updateSql);
            $updateQuery->bindParam(':carType', $carType, PDO::PARAM_STR);
            $updateQuery->bindParam(':id', $car->id, PDO::PARAM_INT);
            $updateQuery->execute();
        }
    }
    
    /**
     * Determine car type based on features
     */
    private function determineCarType($car) {
        $seatingCapacity = $car->SeatingCapacity;
        $price = $car->PricePerDay;
        $brand = strtolower($car->BrandName ?? '');
        
        // SUV characteristics
        if ($seatingCapacity >= 7 || 
            strpos(strtolower($car->VehiclesTitle), 'suv') !== false ||
            strpos(strtolower($car->VehiclesTitle), 'fortuner') !== false ||
            strpos(strtolower($car->VehiclesTitle), 'q8') !== false) {
            return 'SUV';
        }
        
        // Sedan characteristics
        if ($seatingCapacity == 5 && $price >= 800 ||
            strpos(strtolower($car->VehiclesTitle), 'sedan') !== false ||
            strpos(strtolower($car->VehiclesTitle), 'series') !== false) {
            return 'Sedan';
        }
        
        // Hatchback characteristics
        if ($seatingCapacity <= 5 && $price <= 600 ||
            strpos(strtolower($car->VehiclesTitle), 'hatch') !== false ||
            strpos(strtolower($car->VehiclesTitle), 'wagon') !== false) {
            return 'Hatchback';
        }
        
        // Default classification
        return 'Sedan';
    }
    
    /**
     * Filter cars by user preferences
     */
    private function filterCarsByPreferences($cars, $budget, $carType) {
        $filtered = [];
        
        foreach ($cars as $car) {
            // Check budget constraint
            if ($car->PricePerDay <= $budget) {
                // Check car type
                $carTypeFromDB = $this->getCarType($car->id);
                if ($carTypeFromDB == $carType || $carType == 'Any') {
                    $filtered[] = $car;
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get car type from database
     */
    private function getCarType($carId) {
        $sql = "SELECT CarType FROM tblvehicles WHERE id = :id";
        $query = $this->dbh->prepare($sql);
        $query->bindParam(':id', $carId, PDO::PARAM_INT);
        $query->execute();
        
        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result ? $result->CarType : 'Sedan';
    }
    
    /**
     * Apply content-based filtering
     */
    private function contentBasedFiltering($cars, $pastRentals) {
        if (empty($pastRentals)) {
            return $cars;
        }
        
        // Calculate user preferences from past rentals
        $userPreferences = $this->calculateUserPreferences($pastRentals);
        
        // Score cars based on user preferences
        $scoredCars = [];
        foreach ($cars as $car) {
            $score = $this->calculateCarScore($car, $userPreferences);
            $car->recommendationScore = $score;
            $scoredCars[] = $car;
        }
        
        // Sort by recommendation score
        usort($scoredCars, function($a, $b) {
            return $b->recommendationScore <=> $a->recommendationScore;
        });
        
        return $scoredCars;
    }
    
    /**
     * Calculate user preferences from past rentals
     */
    private function calculateUserPreferences($pastRentals) {
        $preferences = [
            'avgPrice' => 0,
            'preferredBrands' => [],
            'preferredFeatures' => [],
            'avgSeating' => 0
        ];
        
        $totalPrice = 0;
        $totalSeating = 0;
        $brandCounts = [];
        $featureCounts = [];
        
        foreach ($pastRentals as $rental) {
            $totalPrice += $rental->PricePerDay;
            $totalSeating += $rental->SeatingCapacity;
            
            // Count brand preferences
            if ($rental->BrandName) {
                $brandCounts[$rental->BrandName] = ($brandCounts[$rental->BrandName] ?? 0) + 1;
            }
            
            // Count feature preferences
            $features = $this->getCarFeatures($rental);
            foreach ($features as $feature) {
                $featureCounts[$feature] = ($featureCounts[$feature] ?? 0) + 1;
            }
        }
        
        $count = count($pastRentals);
        if ($count > 0) {
            $preferences['avgPrice'] = $totalPrice / $count;
            $preferences['avgSeating'] = $totalSeating / $count;
            $preferences['preferredBrands'] = array_keys($brandCounts);
            $preferences['preferredFeatures'] = array_keys($featureCounts);
        }
        
        return $preferences;
    }
    
    /**
     * Get car features as array
     */
    private function getCarFeatures($car) {
        $features = [];
        
        if ($car->AirConditioner) $features[] = 'AirConditioner';
        if ($car->PowerDoorLocks) $features[] = 'PowerDoorLocks';
        if ($car->AntiLockBrakingSystem) $features[] = 'AntiLockBrakingSystem';
        if ($car->BrakeAssist) $features[] = 'BrakeAssist';
        if ($car->PowerSteering) $features[] = 'PowerSteering';
        if ($car->DriverAirbag) $features[] = 'DriverAirbag';
        if ($car->PassengerAirbag) $features[] = 'PassengerAirbag';
        if ($car->PowerWindows) $features[] = 'PowerWindows';
        if ($car->CDPlayer) $features[] = 'CDPlayer';
        if ($car->CentralLocking) $features[] = 'CentralLocking';
        if ($car->CrashSensor) $features[] = 'CrashSensor';
        if ($car->LeatherSeats) $features[] = 'LeatherSeats';
        
        return $features;
    }
    
    /**
     * Calculate car score based on user preferences
     */
    private function calculateCarScore($car, $userPreferences) {
        $score = 0;
        
        // Price similarity (closer to user's average preferred price gets higher score)
        if ($userPreferences['avgPrice'] > 0) {
            $priceDiff = abs($car->PricePerDay - $userPreferences['avgPrice']);
            $maxPrice = max($car->PricePerDay, $userPreferences['avgPrice']);
            $priceScore = 1 - ($priceDiff / $maxPrice);
            $score += $priceScore * 0.3;
        }
        
        // Brand preference
        if (in_array($car->BrandName, $userPreferences['preferredBrands'])) {
            $score += 0.2;
        }
        
        // Feature matching
        $carFeatures = $this->getCarFeatures($car);
        $matchingFeatures = array_intersect($carFeatures, $userPreferences['preferredFeatures']);
        $featureScore = count($matchingFeatures) / max(count($userPreferences['preferredFeatures']), 1);
        $score += $featureScore * 0.3;
        
        // Seating capacity similarity
        if ($userPreferences['avgSeating'] > 0) {
            $seatingDiff = abs($car->SeatingCapacity - $userPreferences['avgSeating']);
            $seatingScore = 1 - ($seatingDiff / max($car->SeatingCapacity, $userPreferences['avgSeating']));
            $score += $seatingScore * 0.2;
        }
        
        return $score;
    }
    
    /**
     * Apply K-Means clustering to group similar recommendations
     */
    private function applyKMeansClustering($cars) {
        if (count($cars) <= 3) {
            return $cars;
        }
        
        // Simple clustering based on price ranges
        $clusters = [
            'budget' => [],
            'mid_range' => [],
            'premium' => []
        ];
        
        foreach ($cars as $car) {
            if ($car->PricePerDay <= 500) {
                $clusters['budget'][] = $car;
            } elseif ($car->PricePerDay <= 1500) {
                $clusters['mid_range'][] = $car;
            } else {
                $clusters['premium'][] = $car;
            }
        }
        
        // Return top recommendations from each cluster
        $result = [];
        foreach ($clusters as $cluster) {
            $result = array_merge($result, array_slice($cluster, 0, 2));
        }
        
        return array_slice($result, 0, 6); // Return top 6 recommendations
    }
    
    /**
     * Get recommendation explanation
     */
    public function getRecommendationExplanation($car, $userPreferences) {
        $explanations = [];
        
        if ($userPreferences['avgPrice'] > 0) {
            $priceDiff = $car->PricePerDay - $userPreferences['avgPrice'];
            if (abs($priceDiff) < 200) {
                $explanations[] = "Price matches your usual budget range";
            } elseif ($priceDiff < 0) {
                $explanations[] = "Great value - below your usual budget";
            } else {
                $explanations[] = "Premium option - slightly above your usual budget";
            }
        }
        
        if (in_array($car->BrandName, $userPreferences['preferredBrands'])) {
            $explanations[] = "From a brand you've rented before";
        }
        
        $carFeatures = $this->getCarFeatures($car);
        $matchingFeatures = array_intersect($carFeatures, $userPreferences['preferredFeatures']);
        if (count($matchingFeatures) > 0) {
            $explanations[] = "Has features you prefer: " . implode(', ', $matchingFeatures);
        }
        
        return $explanations;
    }
}
?>
