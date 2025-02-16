<?php if (!empty($files)): ?>
    <div class="attached-files">
        <h5>Assignment Materials:</h5>
        <?php foreach ($files as $file): ?>
            <div class="file-item">
                <?php if (strpos($file['file_type'], 'video/') === 0): ?>
                    <div class="video-container">
                        <video controls class="video-player">
                            <source src="../uploads/assignments/<?php echo $assignmentId; ?>/<?php echo $file['file_name']; ?>" 
                                    type="<?php echo $file['file_type']; ?>">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                <?php else: ?>
                    <i class="<?php echo getFileIcon($file['file_type']); ?>"></i>
                    <a href="download.php?file=<?php echo $file['id']; ?>&type=assignment">
                        <?php echo htmlspecialchars($file['original_name']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?> 